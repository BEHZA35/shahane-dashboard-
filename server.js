const express = require("express");
const helmet = require("helmet");
const rateLimit = require("express-rate-limit");

const app = express();
app.disable("x-powered-by");
app.use(helmet({ contentSecurityPolicy: false }));
app.use(express.json({ limit: "32kb" }));
app.use(rateLimit({ windowMs: 60_000, limit: 90, standardHeaders: "draft-8", legacyHeaders: false }));

const required = ["WOOCOMMERCE_CONSUMER_KEY", "WOOCOMMERCE_CONSUMER_SECRET", "ADMIN_API_TOKEN"];
const missing = required.filter(k => !process.env[k]);
const storeUrl = (process.env.WORDPRESS_SITE_URL || "https://shahanee.com").replace(/\/$/, "");

app.get("/api/health", (_req, res) => {
  res.json({
    ok: true,
    service: "shahaneh-admin-api",
    wooConfigured: !missing.includes("WOOCOMMERCE_CONSUMER_KEY") && !missing.includes("WOOCOMMERCE_CONSUMER_SECRET"),
    adminTokenConfigured: !missing.includes("ADMIN_API_TOKEN")
  });
});

function requireAdmin(req, res, next) {
  const token = process.env.ADMIN_API_TOKEN;
  if (!token) return res.status(503).json({ error: "Admin API token is not configured." });
  const supplied = req.get("x-admin-token");
  if (!supplied || supplied.length !== token.length || !require("crypto").timingSafeEqual(Buffer.from(supplied), Buffer.from(token))) {
    return res.status(401).json({ error: "Unauthorized" });
  }
  next();
}

async function woo(path, query = {}) {
  if (missing.length) throw Object.assign(new Error("WooCommerce credentials are not configured on the server."), { status: 503 });
  const url = new URL(storeUrl + "/wp-json/wc/v3/" + path);
  for (const [k, v] of Object.entries(query)) if (v !== undefined) url.searchParams.set(k, String(v));
  const auth = Buffer.from(process.env.WOOCOMMERCE_CONSUMER_KEY + ":" + process.env.WOOCOMMERCE_CONSUMER_SECRET).toString("base64");
  const response = await fetch(url, { headers: { Authorization: "Basic " + auth, Accept: "application/json" }, signal: AbortSignal.timeout(15000) });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw Object.assign(new Error(data.message || "WooCommerce API request failed"), { status: response.status });
  return data;
}

app.get("/api/products", requireAdmin, async (req, res, next) => {
  try { res.json(await woo("products", { per_page: Math.min(Number(req.query.per_page) || 20, 100), page: Math.max(Number(req.query.page) || 1, 1), search: req.query.search || undefined })); }
  catch (e) { next(e); }
});
app.get("/api/orders", requireAdmin, async (req, res, next) => {
  try { res.json(await woo("orders", { per_page: Math.min(Number(req.query.per_page) || 20, 100), page: Math.max(Number(req.query.page) || 1, 1), status: req.query.status || undefined })); }
  catch (e) { next(e); }
});
app.get("/api/customers", requireAdmin, async (req, res, next) => {
  try { res.json(await woo("customers", { per_page: Math.min(Number(req.query.per_page) || 20, 100), page: Math.max(Number(req.query.page) || 1, 1), search: req.query.search || undefined })); }
  catch (e) { next(e); }
});
app.get("/api/reports/sales", requireAdmin, async (req, res, next) => {
  try { res.json(await woo("reports/sales", { date_min: req.query.date_min || undefined, date_max: req.query.date_max || undefined })); }
  catch (e) { next(e); }
});

app.use((err, _req, res, _next) => {
  const status = Number(err.status) || 500;
  res.status(status).json({ error: status >= 500 ? "Server or WooCommerce connection error." : err.message });
});
const port = Number(process.env.PORT) || 3000;
app.listen(port, () => console.log("Shahaneh admin API listening on " + port));
