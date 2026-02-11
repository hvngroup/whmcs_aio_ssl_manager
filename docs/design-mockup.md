import { useState } from "react";

const COLORS = {
  primary: "#1890ff", success: "#52c41a", warning: "#faad14", danger: "#ff4d4f",
  text: "#595959", heading: "#262626", border: "#d9d9d9", bg: "#f5f5f5",
  secondary: "#8c8c8c", white: "#fff",
};

const PROVIDERS = {
  nicsrs: { name: "NicSRS", color: "#1890ff", tier: 1, balance: "$1,245.00" },
  thesslstore: { name: "TheSSLStore", color: "#722ed1", tier: 1, balance: "$3,890.50" },
  gogetssl: { name: "GoGetSSL", color: "#13c2c2", tier: 1, balance: "$2,100.00" },
  ssl2buy: { name: "SSL2Buy", color: "#fa8c16", tier: 2, balance: "$560.00" },
};

const STATS = [
  { label: "Total Orders", value: 847, icon: "📦", color: COLORS.primary, trend: "+12%" },
  { label: "Pending", value: 23, icon: "⏳", color: COLORS.warning, trend: "-3" },
  { label: "Issued", value: 789, icon: "✅", color: COLORS.success, trend: "+28" },
  { label: "Expiring (30d)", value: 35, icon: "⚠️", color: COLORS.danger, trend: "+5" },
];

const ORDERS = [
  { id: "AIO-1847", provider: "nicsrs", domain: "hvn.vn", product: "Sectigo PositiveSSL", client: "HVN Group", status: "Issued", date: "2026-02-10" },
  { id: "AIO-1846", provider: "thesslstore", domain: "*.example.com", product: "DigiCert WildCard", client: "TechCorp", status: "Pending", date: "2026-02-10" },
  { id: "AIO-1845", provider: "gogetssl", domain: "shop.vn", product: "Sectigo EV SSL", client: "ShopVN", status: "Processing", date: "2026-02-09" },
  { id: "AIO-1844", provider: "ssl2buy", domain: "secure.io", product: "Comodo PositiveSSL", client: "SecureIO", status: "Awaiting Config", date: "2026-02-09" },
  { id: "AIO-1843", provider: "nicsrs", domain: "api.cloud.vn", product: "GlobalSign OV SSL", client: "CloudVN", status: "Issued", date: "2026-02-08" },
  { id: "AIO-1842", provider: "thesslstore", domain: "bank.vn", product: "DigiCert EV SSL", client: "BankVN", status: "Issued", date: "2026-02-07" },
];

// Canonical products with per-provider pricing (PDR §13.3 structure)
const CANONICAL_PRODUCTS = [
  {
    id: 1, name: "Sectigo PositiveSSL", vendor: "Sectigo", validation: "DV", type: "Single Domain", wildcard: false, san: false,
    whmcsPrice: 24.99,
    providers: [
      { slug: "nicsrs", productName: "Sectigo PositiveSSL", p1: 7.95, p2: 15.90, p3: 23.85, san: null },
      { slug: "gogetssl", productName: "Sectigo PositiveSSL DV", p1: 8.50, p2: 16.00, p3: 24.00, san: null },
      { slug: "thesslstore", productName: "Sectigo PositiveSSL", p1: 9.50, p2: 18.00, p3: 27.00, san: null },
      { slug: "ssl2buy", productName: "Comodo PositiveSSL", p1: 8.00, p2: 15.50, p3: 24.50, san: null },
    ]
  },
  {
    id: 2, name: "Sectigo Essential Wildcard SSL", vendor: "Sectigo", validation: "DV", type: "Wildcard", wildcard: true, san: false,
    whmcsPrice: 149.99,
    providers: [
      { slug: "nicsrs", productName: "Sectigo Essential Wildcard", p1: 59.99, p2: 99.99, p3: 139.99, san: null },
      { slug: "gogetssl", productName: "Sectigo PositiveSSL Wildcard", p1: 49.99, p2: 89.99, p3: 129.99, san: null },
      { slug: "thesslstore", productName: "Sectigo Essential Wildcard", p1: 62.00, p2: 105.00, p3: 148.00, san: null },
      { slug: "ssl2buy", productName: "Comodo Essential Wildcard", p1: 55.00, p2: 95.00, p3: null, san: null },
    ]
  },
  {
    id: 3, name: "Sectigo EV SSL", vendor: "Sectigo", validation: "EV", type: "Single Domain", wildcard: false, san: false,
    whmcsPrice: 199.99,
    providers: [
      { slug: "nicsrs", productName: "Sectigo EV SSL", p1: 69.99, p2: 129.99, p3: 179.99, san: null },
      { slug: "gogetssl", productName: "Sectigo EV SSL", p1: 59.99, p2: 109.99, p3: 159.99, san: null },
      { slug: "thesslstore", productName: "DigiCert Secure Site EV", p1: 295.00, p2: 540.00, p3: 780.00, san: null },
      { slug: "ssl2buy", productName: "Comodo EV SSL", p1: 75.00, p2: null, p3: null, san: null },
    ]
  },
  {
    id: 4, name: "Sectigo Multi-Domain SSL", vendor: "Sectigo", validation: "OV", type: "Multi-Domain", wildcard: false, san: true,
    whmcsPrice: 89.99,
    providers: [
      { slug: "nicsrs", productName: "Sectigo Multi-Domain", p1: 29.99, p2: 54.99, p3: 79.99, san: 12.00 },
      { slug: "gogetssl", productName: "Sectigo Multi-Domain", p1: 32.00, p2: 58.00, p3: 84.00, san: 14.00 },
      { slug: "thesslstore", productName: "DigiCert Multi-Domain", p1: 199.00, p2: 360.00, p3: 510.00, san: 45.00 },
      { slug: "ssl2buy", productName: "Comodo Multi-Domain", p1: 35.00, p2: 60.00, p3: null, san: 15.00 },
    ]
  },
  {
    id: 5, name: "Sectigo InstantSSL", vendor: "Sectigo", validation: "OV", type: "Single Domain", wildcard: false, san: false,
    whmcsPrice: 79.99,
    providers: [
      { slug: "nicsrs", productName: "Sectigo InstantSSL", p1: 19.99, p2: 34.99, p3: 49.99, san: null },
      { slug: "gogetssl", productName: "Sectigo InstantSSL", p1: 22.00, p2: 38.00, p3: 54.00, san: null },
      { slug: "thesslstore", productName: "Sectigo InstantSSL OV", p1: 28.50, p2: 48.00, p3: 68.00, san: null },
      { slug: "ssl2buy", productName: "Comodo InstantSSL", p1: 25.00, p2: 42.00, p3: null, san: null },
    ]
  },
  {
    id: 6, name: "DigiCert Secure Site Pro", vendor: "DigiCert", validation: "OV", type: "Single Domain", wildcard: false, san: true,
    whmcsPrice: 499.99,
    providers: [
      { slug: "thesslstore", productName: "DigiCert Secure Site Pro", p1: 389.00, p2: 740.00, p3: 1050.00, san: 89.00 },
      { slug: "nicsrs", productName: "DigiCert Secure Site Pro", p1: 395.00, p2: 750.00, p3: 1080.00, san: 92.00 },
    ]
  },
];

const PROVIDER_LIST = [
  { slug: "nicsrs", name: "NicSRS", tier: 1, active: true, sandbox: false, products: 156, lastSync: "2 min ago", status: "connected" },
  { slug: "thesslstore", name: "TheSSLStore", tier: 1, active: true, sandbox: false, products: 210, lastSync: "5 min ago", status: "connected" },
  { slug: "gogetssl", name: "GoGetSSL", tier: 1, active: true, sandbox: false, products: 89, lastSync: "3 min ago", status: "connected" },
  { slug: "ssl2buy", name: "SSL2Buy", tier: 2, active: true, sandbox: true, products: 45, lastSync: "10 min ago", status: "connected" },
];

const ALL_PRODUCTS = [
  { provider: "nicsrs", code: "sectigo_positivessl", name: "Sectigo PositiveSSL", vendor: "Sectigo", type: "DV", wildcard: false, san: false, price: "$5.99/yr", linked: true },
  { provider: "nicsrs", code: "sectigo_ev", name: "Sectigo EV SSL", vendor: "Sectigo", type: "EV", wildcard: false, san: false, price: "$69.99/yr", linked: true },
  { provider: "thesslstore", code: "digicert_securesite", name: "DigiCert Secure Site", vendor: "DigiCert", type: "OV", wildcard: false, san: true, price: "$175.00/yr", linked: false },
  { provider: "thesslstore", code: "sectigo_essential_wildcard", name: "Sectigo Essential Wildcard", vendor: "Sectigo", type: "DV", wildcard: true, san: false, price: "$62.00/yr", linked: true },
  { provider: "gogetssl", code: "ggssl_domain_ssl", name: "GoGetSSL Domain SSL", vendor: "Sectigo", type: "DV", wildcard: false, san: false, price: "$4.99/yr", linked: false },
  { provider: "gogetssl", code: "ggssl_ev_ssl", name: "Sectigo EV SSL", vendor: "Sectigo", type: "EV", wildcard: false, san: false, price: "$59.99/yr", linked: true },
  { provider: "ssl2buy", code: "comodo_positive", name: "Comodo PositiveSSL", vendor: "Comodo", type: "DV", wildcard: false, san: false, price: "$5.49/yr", linked: false },
  { provider: "ssl2buy", code: "globalsign_ov", name: "GlobalSign OV SSL", vendor: "GlobalSign", type: "OV", wildcard: false, san: false, price: "$129.00/yr", linked: false },
];

// ── Legacy migration demo data ──
const LEGACY_MODULES = [
  { module: "nicsrs_ssl", name: "NicSRS SSL", orders: 312, claimable: 312, status: "detected" },
  { module: "SSLCENTERWHMCS", name: "GoGetSSL (SSLCENTER)", orders: 198, claimable: 198, status: "detected" },
  { module: "thesslstore_ssl", name: "TheSSLStore SSL", orders: 245, claimable: 245, status: "detected" },
  { module: "ssl2buy", name: "SSL2Buy", orders: 92, claimable: 92, status: "detected" },
];

const IMPORT_HISTORY = [
  { id: "IMP-001", type: "Migration", source: "nicsrs_ssl", count: 50, status: "Completed", date: "2026-02-10 14:30" },
  { id: "IMP-002", type: "API Import", source: "GoGetSSL", count: 12, status: "Completed", date: "2026-02-10 15:10" },
  { id: "IMP-003", type: "CSV Bulk", source: "Upload", count: 35, status: "Processing", date: "2026-02-11 09:00" },
];

// ── Report demo data ──
const REVENUE_BY_PROVIDER = [
  { provider: "nicsrs", name: "NicSRS", orders: 312, revenue: 18450.00, cost: 4890.00, profit: 13560.00, margin: 73.5 },
  { provider: "thesslstore", name: "TheSSLStore", orders: 245, revenue: 42300.00, cost: 28100.00, profit: 14200.00, margin: 33.6 },
  { provider: "gogetssl", name: "GoGetSSL", orders: 198, revenue: 12800.00, cost: 3200.00, profit: 9600.00, margin: 75.0 },
  { provider: "ssl2buy", name: "SSL2Buy", orders: 92, revenue: 8500.00, cost: 4100.00, profit: 4400.00, margin: 51.8 },
];

const EXPIRY_FORECAST = [
  { period: "Next 7 days", count: 8, domains: ["api.hvn.vn", "shop.vn", "mail.example.com"] },
  { period: "8–30 days", count: 27, domains: ["bank.vn", "secure.io", "cloud.vn"] },
  { period: "31–60 days", count: 45, domains: ["dashboard.app", "portal.vn"] },
  { period: "61–90 days", count: 62, domains: ["*.example.com", "cdn.hvn.vn"] },
];

const TOP_PRODUCTS = [
  { name: "Sectigo PositiveSSL", orders: 234, revenue: 5836.66, pct: 28 },
  { name: "DigiCert Secure Site", orders: 89, revenue: 22225.00, pct: 27 },
  { name: "Sectigo EV SSL", orders: 78, revenue: 5459.22, pct: 7 },
  { name: "Sectigo Essential Wildcard", orders: 65, revenue: 3899.35, pct: 5 },
  { name: "GoGetSSL Domain SSL", orders: 112, revenue: 558.88, pct: 1 },
];

const Badge = ({ children, color, bg }) => (
  <span style={{ display: "inline-flex", alignItems: "center", padding: "2px 8px", borderRadius: 3, fontSize: 11, fontWeight: 600, color: color || COLORS.text, background: bg || COLORS.bg, border: `1px solid ${color || COLORS.border}22`, whiteSpace: "nowrap" }}>{children}</span>
);

const ProviderBadge = ({ slug }) => {
  const p = PROVIDERS[slug];
  if (!p) return null;
  return <Badge color={p.color} bg={`${p.color}12`}>{p.name}{p.tier === 2 && <span style={{ marginLeft: 4, fontSize: 9, opacity: 0.7 }}>T2</span>}</Badge>;
};

const StatusBadge = ({ status }) => {
  const map = { "Issued": { c: COLORS.success, b: "#f6ffed" }, "Pending": { c: COLORS.warning, b: "#fffbe6" }, "Processing": { c: COLORS.primary, b: "#e6f7ff" }, "Awaiting Config": { c: "#fa8c16", b: "#fff7e6" }, "Expired": { c: COLORS.danger, b: "#fff1f0" }, "connected": { c: COLORS.success, b: "#f6ffed" }, "Completed": { c: COLORS.success, b: "#f6ffed" }, "detected": { c: COLORS.primary, b: "#e6f7ff" } };
  const s = map[status] || { c: COLORS.secondary, b: COLORS.bg };
  return <Badge color={s.c} bg={s.b}>{status}</Badge>;
};

const StatCard = ({ stat }) => (
  <div style={{ flex: 1, minWidth: 180, background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: "16px 20px", borderTop: `3px solid ${stat.color}` }}>
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
      <div>
        <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 4 }}>{stat.label}</div>
        <div style={{ fontSize: 28, fontWeight: 700, color: COLORS.heading }}>{stat.value}</div>
      </div>
      <span style={{ fontSize: 24 }}>{stat.icon}</span>
    </div>
    <div style={{ fontSize: 11, color: stat.trend.startsWith("+") ? COLORS.success : COLORS.danger, marginTop: 4 }}>
      {stat.trend.startsWith("+") ? "↑" : "↓"} {stat.trend} vs last month
    </div>
  </div>
);

const ProviderDonut = () => {
  const data = [
    { name: "NicSRS", value: 37, color: PROVIDERS.nicsrs.color },
    { name: "TheSSLStore", value: 29, color: PROVIDERS.thesslstore.color },
    { name: "GoGetSSL", value: 23, color: PROVIDERS.gogetssl.color },
    { name: "SSL2Buy", value: 11, color: PROVIDERS.ssl2buy.color },
  ];
  let cum = 0;
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 24 }}>
      <svg width="120" height="120" viewBox="0 0 36 36">
        {data.map((d, i) => {
          const offset = cum; cum += d.value;
          return <circle key={i} cx="18" cy="18" r="14" fill="none" stroke={d.color} strokeWidth="5" strokeDasharray={`${d.value} ${100 - d.value}`} strokeDashoffset={-offset} />;
        })}
        <text x="18" y="17" textAnchor="middle" fontSize="5" fontWeight="700" fill={COLORS.heading}>847</text>
        <text x="18" y="22" textAnchor="middle" fontSize="3" fill={COLORS.secondary}>total</text>
      </svg>
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {data.map(d => (
          <div key={d.name} style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 12 }}>
            <div style={{ width: 10, height: 10, borderRadius: 2, background: d.color }} />
            <span style={{ color: COLORS.text }}>{d.name}</span>
            <span style={{ fontWeight: 600, color: COLORS.heading }}>{d.value}%</span>
          </div>
        ))}
      </div>
    </div>
  );
};

const MonthlyBars = () => {
  const months = ["Sep", "Oct", "Nov", "Dec", "Jan", "Feb"];
  const values = [52, 68, 75, 82, 91, 98];
  const max = Math.max(...values);
  return (
    <div style={{ display: "flex", alignItems: "flex-end", gap: 12, height: 140, padding: "0 8px" }}>
      {months.map((m, i) => {
        const h = (values[i] / max) * 110;
        return (
          <div key={m} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center" }}>
            <div style={{ fontSize: 11, fontWeight: 600, color: COLORS.heading, marginBottom: 4 }}>{values[i]}</div>
            <div style={{ width: "100%", borderRadius: "4px 4px 0 0", background: i === months.length - 1 ? COLORS.primary : "#d6e4ff", height: h, transition: "height 0.3s" }}>
              {[0, 1, 2, 3].map(j => {
                const colors = [PROVIDERS.nicsrs.color, PROVIDERS.thesslstore.color, PROVIDERS.gogetssl.color, PROVIDERS.ssl2buy.color];
                const pcts = [0.37, 0.29, 0.23, 0.11];
                return <div key={j} style={{ width: "100%", height: `${pcts[j] * 100}%`, background: colors[j], opacity: i === months.length - 1 ? 1 : 0.5, borderRadius: j === 0 ? "4px 4px 0 0" : j === 3 ? "0 0 2px 2px" : 0 }} />;
              })}
            </div>
            <div style={{ fontSize: 10, color: COLORS.secondary, marginTop: 4 }}>{m}</div>
          </div>
        );
      })}
    </div>
  );
};

// ── Mini bar for reports ──
const MiniBar = ({ value, max, color }) => (
  <div style={{ width: "100%", height: 6, background: "#f0f0f0", borderRadius: 3, overflow: "hidden" }}>
    <div style={{ width: `${(value / max) * 100}%`, height: "100%", background: color || COLORS.primary, borderRadius: 3 }} />
  </div>
);

// ── Pages ──

const DASH_ORDERS = [
  { id: "AIO-1847", provider: "nicsrs", domain: "hvn.vn", product: "Sectigo PositiveSSL", client: "HVN Group", clientId: 1, serviceId: 452, serviceName: "Sectigo PositiveSSL - hvn.vn", status: "Issued", date: "2026-02-10" },
  { id: "AIO-1846", provider: "thesslstore", domain: "*.example.com", product: "DigiCert WildCard", client: "TechCorp", clientId: 15, serviceId: 453, serviceName: "DigiCert WildCard - *.example.com", status: "Pending", date: "2026-02-10" },
  { id: "AIO-1845", provider: "gogetssl", domain: "shop.vn", product: "Sectigo EV SSL", client: "ShopVN", clientId: 22, serviceId: 454, serviceName: "Sectigo EV SSL - shop.vn", status: "Processing", date: "2026-02-09" },
  { id: "AIO-1844", provider: "ssl2buy", domain: "secure.io", product: "Comodo PositiveSSL", client: "SecureIO", clientId: 38, serviceId: 455, serviceName: "Comodo PositiveSSL - secure.io", status: "Awaiting Config", date: "2026-02-09" },
  { id: "AIO-1843", provider: "nicsrs", domain: "api.cloud.vn", product: "GlobalSign OV SSL", client: "CloudVN", clientId: 44, serviceId: 456, serviceName: "GlobalSign OV SSL - api.cloud.vn", status: "Issued", date: "2026-02-08" },
  { id: "AIO-1842", provider: "thesslstore", domain: "bank.vn", product: "DigiCert EV SSL", client: "BankVN", clientId: 51, serviceId: 457, serviceName: "DigiCert EV SSL - bank.vn", status: "Issued", date: "2026-02-07" },
];

const DASH_EXPIRING = [
  { id: "AIO-1801", provider: "nicsrs", domain: "api.hvn.vn", product: "Sectigo PositiveSSL", client: "HVN Group", clientId: 1, serviceId: 401, serviceName: "Sectigo PositiveSSL - api.hvn.vn", expiry: "2026-02-15", days: 3 },
  { id: "AIO-1789", provider: "gogetssl", domain: "shop.vn", product: "Sectigo EV SSL", client: "ShopVN", clientId: 22, serviceId: 389, serviceName: "Sectigo EV SSL - shop.vn", expiry: "2026-02-24", days: 12 },
  { id: "AIO-1756", provider: "thesslstore", domain: "mail.example.com", product: "DigiCert Basic OV", client: "TechCorp", clientId: 15, serviceId: 356, serviceName: "DigiCert Basic OV - mail.example.com", expiry: "2026-03-04", days: 20 },
  { id: "AIO-1742", provider: "ssl2buy", domain: "secure.io", product: "Comodo PositiveSSL", client: "SecureIO", clientId: 38, serviceId: 342, serviceName: "Comodo PositiveSSL - secure.io", expiry: "2026-03-09", days: 25 },
  { id: "AIO-1731", provider: "nicsrs", domain: "cdn.hvn.vn", product: "Sectigo Essential Wildcard", client: "HVN Group", clientId: 1, serviceId: 331, serviceName: "Sectigo Wildcard - cdn.hvn.vn", expiry: "2026-03-12", days: 28 },
];

const STATUS_DIST = [
  { status: "Issued", count: 789, pct: 93.2, color: COLORS.success },
  { status: "Pending", count: 23, pct: 2.7, color: COLORS.warning },
  { status: "Processing", count: 12, pct: 1.4, color: COLORS.primary },
  { status: "Awaiting Config", count: 8, pct: 0.9, color: "#fa8c16" },
  { status: "Expired", count: 10, pct: 1.2, color: COLORS.danger },
  { status: "Revoked", count: 5, pct: 0.6, color: COLORS.secondary },
];

const ClientLink = ({ name, clientId }) => (
  <a href={`#clientssummary.php?userid=${clientId}`} style={{ color: COLORS.primary, textDecoration: "none", fontWeight: 400 }} title={`View client #${clientId}`}>{name}</a>
);
const ServiceLink = ({ serviceId, serviceName }) => (
  <a href={`#clientsservices.php?id=${serviceId}`} style={{ color: "#722ed1", textDecoration: "none", fontSize: 11 }} title={`Service #${serviceId}`}>{serviceName}</a>
);

const DashboardPage = () => (
  <div>
    <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
      {STATS.map(s => <StatCard key={s.label} stat={s} />)}
    </div>
    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 16, marginBottom: 20 }}>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>📊 Orders by Provider</div>
        <ProviderDonut />
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>📋 Certificate Status Distribution</div>
        <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
          {STATUS_DIST.map(s => (
            <div key={s.status} style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 12 }}>
              <span style={{ width: 90, color: COLORS.text, fontWeight: 500 }}>{s.status}</span>
              <div style={{ flex: 1, height: 8, background: "#f0f0f0", borderRadius: 4, overflow: "hidden" }}>
                <div style={{ width: `${s.pct}%`, minWidth: s.pct > 0 ? 4 : 0, height: "100%", background: s.color, borderRadius: 4 }} />
              </div>
              <span style={{ width: 36, textAlign: "right", fontWeight: 600, color: COLORS.heading }}>{s.count}</span>
              <span style={{ width: 42, textAlign: "right", color: COLORS.secondary, fontSize: 11 }}>{s.pct}%</span>
            </div>
          ))}
        </div>
        <div style={{ marginTop: 12, padding: "8px 0", borderTop: `1px solid ${COLORS.border}`, display: "flex", justifyContent: "space-between", fontSize: 11, color: COLORS.secondary }}>
          <span>Total: <strong style={{ color: COLORS.heading }}>847</strong> certificates</span>
          <span>Active rate: <strong style={{ color: COLORS.success }}>93.2%</strong></span>
        </div>
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>📈 Monthly Trend</div>
        <MonthlyBars />
      </div>
    </div>

    {/* Recent Orders — full width */}
    <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, marginBottom: 20 }}>
      <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading }}>🕐 Recent Orders</div>
        <a href="#" style={{ fontSize: 12, color: COLORS.primary, textDecoration: "none" }}>View All →</a>
      </div>
      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Order ID", "Provider", "Domain", "Product", "Client", "Service", "Status", "Date", "Actions"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, whiteSpace: "nowrap" }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{DASH_ORDERS.map(o => (
            <tr key={o.id} style={{ borderBottom: "1px solid #f0f0f0" }}>
              <td style={{ padding: "10px 12px" }}><a href="#" style={{ color: COLORS.primary, textDecoration: "none", fontWeight: 500 }}>{o.id}</a></td>
              <td style={{ padding: "10px 12px" }}><ProviderBadge slug={o.provider} /></td>
              <td style={{ padding: "10px 12px", fontFamily: "monospace", fontSize: 11 }}>{o.domain}</td>
              <td style={{ padding: "10px 12px" }}>{o.product}</td>
              <td style={{ padding: "10px 12px" }}><ClientLink name={o.client} clientId={o.clientId} /></td>
              <td style={{ padding: "10px 12px" }}><ServiceLink serviceId={o.serviceId} serviceName={o.serviceName} /></td>
              <td style={{ padding: "10px 12px" }}><StatusBadge status={o.status} /></td>
              <td style={{ padding: "10px 12px", color: COLORS.secondary, whiteSpace: "nowrap" }}>{o.date}</td>
              <td style={{ padding: "10px 12px" }}>
                <div style={{ display: "flex", gap: 4 }}>
                  <button style={{ padding: "4px 8px", background: "#e6f7ff", color: COLORS.primary, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>View</button>
                  <button style={{ padding: "4px 8px", background: "#f5f5f5", color: COLORS.text, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Refresh</button>
                </div>
              </td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </div>

    {/* Expiring Soon — full width */}
    <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
      <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading }}>⚠️ Expiring Soon <Badge color={COLORS.danger} bg="#fff1f0">{DASH_EXPIRING.length} certs within 30 days</Badge></div>
        <a href="#" style={{ fontSize: 12, color: COLORS.primary, textDecoration: "none" }}>View Forecast →</a>
      </div>
      <div style={{ overflowX: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Order ID", "Provider", "Domain", "Product", "Client", "Service", "Expiry Date", "Days Left", "Action"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, whiteSpace: "nowrap" }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{DASH_EXPIRING.map(e => (
            <tr key={e.id} style={{ borderBottom: "1px solid #f0f0f0", background: e.days <= 7 ? "#fff1f005" : "transparent" }}>
              <td style={{ padding: "10px 12px" }}><a href="#" style={{ color: COLORS.primary, textDecoration: "none", fontWeight: 500 }}>{e.id}</a></td>
              <td style={{ padding: "10px 12px" }}><ProviderBadge slug={e.provider} /></td>
              <td style={{ padding: "10px 12px", fontFamily: "monospace", fontSize: 11 }}>{e.domain}</td>
              <td style={{ padding: "10px 12px" }}>{e.product}</td>
              <td style={{ padding: "10px 12px" }}><ClientLink name={e.client} clientId={e.clientId} /></td>
              <td style={{ padding: "10px 12px" }}><ServiceLink serviceId={e.serviceId} serviceName={e.serviceName} /></td>
              <td style={{ padding: "10px 12px", color: COLORS.secondary, whiteSpace: "nowrap" }}>{e.expiry}</td>
              <td style={{ padding: "10px 12px" }}>
                <Badge color={e.days <= 7 ? COLORS.danger : e.days <= 14 ? COLORS.warning : COLORS.primary} bg={e.days <= 7 ? "#fff1f0" : e.days <= 14 ? "#fffbe6" : "#e6f7ff"}>
                  {e.days} days
                </Badge>
              </td>
              <td style={{ padding: "10px 12px" }}>
                <button style={{ padding: "4px 10px", background: "#f6ffed", color: COLORS.success, border: `1px solid ${COLORS.success}22`, borderRadius: 3, fontSize: 11, fontWeight: 500, cursor: "pointer" }}>🔄 Renew</button>
              </td>
            </tr>
          ))}</tbody>
        </table>
      </div>
    </div>
  </div>
);

const ProvidersPage = () => (
  <div>
    <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 16 }}>
      <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading }}>Configured Providers</div>
      <button style={{ padding: "7px 16px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, fontWeight: 600, cursor: "pointer" }}>+ Add Provider</button>
    </div>
    <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
        <thead><tr style={{ background: "#fafafa" }}>
          {["Provider", "Tier", "Status", "Mode", "Products", "Last Sync", "Balance", "Actions"].map(h => (
            <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
          ))}
        </tr></thead>
        <tbody>{PROVIDER_LIST.map(p => (
          <tr key={p.slug} style={{ borderBottom: "1px solid #f0f0f0" }}>
            <td style={{ padding: "10px 12px" }}><ProviderBadge slug={p.slug} /></td>
            <td style={{ padding: "10px 12px" }}><Badge color={p.tier === 1 ? COLORS.primary : COLORS.warning} bg={p.tier === 1 ? "#e6f7ff" : "#fff7e6"}>Tier {p.tier}</Badge></td>
            <td style={{ padding: "10px 12px" }}><span style={{ display: "inline-flex", alignItems: "center", gap: 4, color: COLORS.success, fontSize: 12 }}>● Connected</span></td>
            <td style={{ padding: "10px 12px" }}>{p.sandbox ? <Badge color={COLORS.warning} bg="#fffbe6">Sandbox</Badge> : <span style={{ color: COLORS.secondary }}>Live</span>}</td>
            <td style={{ padding: "10px 12px", fontWeight: 600 }}>{p.products}</td>
            <td style={{ padding: "10px 12px", color: COLORS.secondary }}>{p.lastSync}</td>
            <td style={{ padding: "10px 12px", fontWeight: 600 }}>{PROVIDERS[p.slug].balance}</td>
            <td style={{ padding: "10px 12px" }}>
              <div style={{ display: "flex", gap: 4 }}>
                <button style={{ padding: "4px 8px", background: "#e6f7ff", color: COLORS.primary, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Sync</button>
                <button style={{ padding: "4px 8px", background: "#f5f5f5", color: COLORS.text, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Edit</button>
                <button style={{ padding: "4px 8px", background: "#fff1f0", color: COLORS.danger, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Disable</button>
              </div>
            </td>
          </tr>
        ))}</tbody>
      </table>
    </div>
  </div>
);

const ProductsPage = () => {
  const [filterProv, setFilterProv] = useState("all");
  const [filterVendor, setFilterVendor] = useState("all");
  const [filterType, setFilterType] = useState("all");
  const [filterLinked, setFilterLinked] = useState("all");
  const [search, setSearch] = useState("");

  const filtered = ALL_PRODUCTS.filter(p => {
    if (filterProv !== "all" && p.provider !== filterProv) return false;
    if (filterVendor !== "all" && p.vendor !== filterVendor) return false;
    if (filterType !== "all" && p.type !== filterType) return false;
    if (filterLinked === "linked" && !p.linked) return false;
    if (filterLinked === "unlinked" && p.linked) return false;
    if (search && !p.name.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  const vendors = [...new Set(ALL_PRODUCTS.map(p => p.vendor))];

  return (
    <div>
      <div style={{ display: "flex", gap: 8, marginBottom: 16, flexWrap: "wrap", alignItems: "center" }}>
        {["all", ...Object.keys(PROVIDERS)].map(k => (
          <button key={k} onClick={() => setFilterProv(k)} style={{ padding: "6px 14px", background: filterProv === k ? COLORS.primary : COLORS.white, color: filterProv === k ? COLORS.white : COLORS.text, border: `1px solid ${filterProv === k ? COLORS.primary : COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>
            {k === "all" ? "All Providers" : PROVIDERS[k].name}
          </button>
        ))}
        <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
          <button style={{ padding: "6px 14px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔄 Sync All</button>
          <button style={{ padding: "6px 14px", background: "#f9f0ff", color: "#722ed1", border: "1px solid #722ed122", borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔗 Product Mapping</button>
        </div>
      </div>
      <div style={{ display: "flex", gap: 8, marginBottom: 16, flexWrap: "wrap", alignItems: "center" }}>
        <select value={filterVendor} onChange={e => setFilterVendor(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Vendors</option>
          {vendors.map(v => <option key={v} value={v}>{v}</option>)}
        </select>
        <select value={filterType} onChange={e => setFilterType(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Types</option>
          <option value="DV">DV</option><option value="OV">OV</option><option value="EV">EV</option>
        </select>
        <select value={filterLinked} onChange={e => setFilterLinked(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Status</option>
          <option value="linked">✅ Linked</option><option value="unlinked">○ Unlinked</option>
        </select>
        <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Search product name..." style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, width: 220 }} />
        {(filterVendor !== "all" || filterType !== "all" || filterLinked !== "all" || search) && (
          <button onClick={() => { setFilterVendor("all"); setFilterType("all"); setFilterLinked("all"); setSearch(""); }} style={{ padding: "7px 12px", background: "#fff1f0", color: COLORS.danger, border: `1px solid ${COLORS.danger}22`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>✕ Clear Filters</button>
        )}
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Provider", "Product Name", "Vendor", "Type", "Wildcard", "SAN", "Price", "Mapping", "Action"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: h === "Wildcard" || h === "SAN" ? "center" : "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{filtered.map((p, i) => (
            <tr key={i} style={{ borderBottom: "1px solid #f0f0f0" }}>
              <td style={{ padding: "10px 12px" }}><ProviderBadge slug={p.provider} /></td>
              <td style={{ padding: "10px 12px", fontWeight: 500 }}>{p.name}</td>
              <td style={{ padding: "10px 12px" }}>{p.vendor}</td>
              <td style={{ padding: "10px 12px" }}><Badge color={p.type === "EV" ? "#722ed1" : p.type === "OV" ? COLORS.primary : COLORS.success} bg={p.type === "EV" ? "#f9f0ff" : p.type === "OV" ? "#e6f7ff" : "#f6ffed"}>{p.type}</Badge></td>
              <td style={{ padding: "10px 12px", textAlign: "center" }}>{p.wildcard ? "✅" : "—"}</td>
              <td style={{ padding: "10px 12px", textAlign: "center" }}>{p.san ? "✅" : "—"}</td>
              <td style={{ padding: "10px 12px", fontWeight: 600, color: COLORS.heading }}>{p.price}</td>
              <td style={{ padding: "10px 12px" }}>{p.linked ? <span style={{ color: COLORS.success }}>● Linked</span> : <span style={{ color: COLORS.secondary }}>○ Unlinked</span>}</td>
              <td style={{ padding: "10px 12px" }}>
                <button style={{ padding: "4px 8px", background: p.linked ? "#f5f5f5" : "#e6f7ff", color: p.linked ? COLORS.text : COLORS.primary, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>{p.linked ? "Unlink" : "Link"}</button>
              </td>
            </tr>
          ))}</tbody>
        </table>
      </div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: 12, fontSize: 12, color: COLORS.secondary }}>
        <span>Showing {filtered.length} of {ALL_PRODUCTS.length} products</span>
        <div style={{ display: "flex", gap: 4 }}>
          {[1, 2, 3].map(p => <button key={p} style={{ padding: "4px 10px", background: p === 1 ? COLORS.primary : COLORS.white, color: p === 1 ? COLORS.white : COLORS.text, border: `1px solid ${p === 1 ? COLORS.primary : COLORS.border}`, borderRadius: 3, fontSize: 12, cursor: "pointer" }}>{p}</button>)}
        </div>
      </div>
    </div>
  );
};

const ComparePage = () => {
  const [filterType, setFilterType] = useState("all");
  const [filterVendor, setFilterVendor] = useState("all");
  const [expanded, setExpanded] = useState(CANONICAL_PRODUCTS.map(p => p.id));

  const filtered = CANONICAL_PRODUCTS.filter(p => {
    if (filterType !== "all") {
      if (filterType === "DV" && p.validation !== "DV") return false;
      if (filterType === "OV" && p.validation !== "OV") return false;
      if (filterType === "EV" && p.validation !== "EV") return false;
      if (filterType === "Wildcard" && !p.wildcard) return false;
      if (filterType === "Multi-Domain" && p.type !== "Multi-Domain") return false;
    }
    if (filterVendor !== "all" && p.vendor !== filterVendor) return false;
    return true;
  });

  const findBest = (providers, key) => {
    let min = Infinity, slug = "";
    providers.forEach(pr => { if (pr[key] != null && pr[key] < min) { min = pr[key]; slug = pr.slug; } });
    return { slug, value: min === Infinity ? null : min };
  };

  const toggle = (id) => setExpanded(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);

  return (
    <div>
      <div style={{ display: "flex", gap: 8, marginBottom: 16, alignItems: "center", flexWrap: "wrap" }}>
        <select value={filterType} onChange={e => setFilterType(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Types</option><option value="DV">DV</option><option value="OV">OV</option><option value="EV">EV</option><option value="Wildcard">Wildcard</option><option value="Multi-Domain">Multi-Domain</option>
        </select>
        <select value={filterVendor} onChange={e => setFilterVendor(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Vendors</option><option value="Sectigo">Sectigo</option><option value="DigiCert">DigiCert</option><option value="GlobalSign">GlobalSign</option>
        </select>
        <input type="text" placeholder="Search product name..." style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, width: 200 }} />
        <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
          <button onClick={() => setExpanded(filtered.map(p => p.id))} style={{ padding: "7px 12px", background: "#f5f5f5", color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>Expand All</button>
          <button onClick={() => setExpanded([])} style={{ padding: "7px 12px", background: "#f5f5f5", color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>Collapse All</button>
          <button style={{ padding: "7px 16px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>📥 Export CSV</button>
        </div>
      </div>

      <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 12 }}>
        Showing {filtered.length} of {CANONICAL_PRODUCTS.length} canonical products • Prices cached 2 hours ago • All prices in USD (reseller cost)
      </div>

      {filtered.map(product => {
        const isOpen = expanded.includes(product.id);
        const best1 = findBest(product.providers, "p1");
        const best2 = findBest(product.providers, "p2");
        const best3 = findBest(product.providers, "p3");
        const bestSan = findBest(product.providers, "san");
        const bestMargin = best1.value != null ? (product.whmcsPrice - best1.value).toFixed(2) : null;
        const valColor = product.validation === "EV" ? "#722ed1" : product.validation === "OV" ? COLORS.primary : COLORS.success;
        const valBg = product.validation === "EV" ? "#f9f0ff" : product.validation === "OV" ? "#e6f7ff" : "#f6ffed";

        return (
          <div key={product.id} style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, marginBottom: 12, overflow: "hidden" }}>
            {/* Product Header */}
            <div onClick={() => toggle(product.id)} style={{ padding: "14px 16px", background: "#fafafa", borderBottom: isOpen ? `1px solid ${COLORS.border}` : "none", cursor: "pointer", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                <span style={{ fontSize: 13, color: COLORS.secondary, transition: "transform 0.2s", transform: isOpen ? "rotate(90deg)" : "rotate(0deg)", display: "inline-block" }}>▶</span>
                <div>
                  <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading }}>{product.name}</div>
                  <div style={{ display: "flex", gap: 8, marginTop: 4, alignItems: "center" }}>
                    <Badge color={valColor} bg={valBg}>{product.validation}</Badge>
                    <span style={{ fontSize: 11, color: COLORS.secondary }}>Type: {product.type}</span>
                    {product.wildcard && <Badge color={COLORS.warning} bg="#fffbe6">Wildcard</Badge>}
                    {product.san && <Badge color="#722ed1" bg="#f9f0ff">SAN</Badge>}
                    <span style={{ fontSize: 11, color: COLORS.secondary }}>Vendor: <strong>{product.vendor}</strong></span>
                  </div>
                </div>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: 16, fontSize: 12 }}>
                <div style={{ textAlign: "right" }}>
                  <div style={{ color: COLORS.secondary, fontSize: 10, textTransform: "uppercase" }}>Best Price</div>
                  <div style={{ fontWeight: 700, color: COLORS.success, fontSize: 16 }}>{best1.value != null ? `${best1.value.toFixed(2)}` : "—"}<span style={{ fontSize: 11, fontWeight: 400, color: COLORS.secondary }}>/yr</span></div>
                </div>
                <div style={{ textAlign: "right" }}>
                  <div style={{ color: COLORS.secondary, fontSize: 10, textTransform: "uppercase" }}>Providers</div>
                  <div style={{ fontWeight: 600, color: COLORS.heading }}>{product.providers.length}</div>
                </div>
              </div>
            </div>

            {/* Expanded: Price Comparison Table (PDR §13.3) */}
            {isOpen && (
              <div style={{ padding: 0 }}>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
                  <thead>
                    <tr style={{ background: "#f5f7fa" }}>
                      <th style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: "28%" }}>Provider</th>
                      <th style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: "18%" }}>1 Year</th>
                      <th style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: "18%" }}>2 Years</th>
                      <th style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: "18%" }}>3 Years</th>
                      <th style={{ padding: "10px 16px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: "18%" }}>SAN (per/yr)</th>
                    </tr>
                  </thead>
                  <tbody>
                    {product.providers.map((pr, pi) => {
                      const is1Best = pr.p1 != null && pr.slug === best1.slug;
                      const is2Best = pr.p2 != null && pr.slug === best2.slug;
                      const is3Best = pr.p3 != null && pr.slug === best3.slug;
                      const isSanBest = pr.san != null && pr.slug === bestSan.slug;
                      return (
                        <tr key={pr.slug} style={{ borderBottom: pi < product.providers.length - 1 ? "1px solid #f0f0f0" : "none" }}>
                          <td style={{ padding: "12px 16px" }}>
                            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                              <ProviderBadge slug={pr.slug} />
                              <span style={{ fontSize: 11, color: COLORS.secondary }}>{pr.productName !== product.name ? pr.productName : ""}</span>
                            </div>
                          </td>
                          <td style={{ padding: "12px 16px", background: is1Best ? "#f6ffed" : "transparent" }}>
                            {pr.p1 != null ? (
                              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                <span style={{ fontWeight: 600, color: is1Best ? COLORS.success : COLORS.heading }}>${pr.p1.toFixed(2)}</span>
                                {is1Best && <span style={{ fontSize: 9, background: COLORS.success, color: COLORS.white, padding: "1px 5px", borderRadius: 2, fontWeight: 600 }}>★ BEST</span>}
                              </div>
                            ) : <span style={{ color: COLORS.secondary }}>—</span>}
                          </td>
                          <td style={{ padding: "12px 16px", background: is2Best ? "#f6ffed" : "transparent" }}>
                            {pr.p2 != null ? (
                              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                <span style={{ fontWeight: 600, color: is2Best ? COLORS.success : COLORS.heading }}>${pr.p2.toFixed(2)}</span>
                                {is2Best && <span style={{ fontSize: 9, background: COLORS.success, color: COLORS.white, padding: "1px 5px", borderRadius: 2, fontWeight: 600 }}>★ BEST</span>}
                              </div>
                            ) : <span style={{ color: COLORS.secondary }}>—</span>}
                          </td>
                          <td style={{ padding: "12px 16px", background: is3Best ? "#f6ffed" : "transparent" }}>
                            {pr.p3 != null ? (
                              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                <span style={{ fontWeight: 600, color: is3Best ? COLORS.success : COLORS.heading }}>${pr.p3.toFixed(2)}</span>
                                {is3Best && <span style={{ fontSize: 9, background: COLORS.success, color: COLORS.white, padding: "1px 5px", borderRadius: 2, fontWeight: 600 }}>★ BEST</span>}
                              </div>
                            ) : <span style={{ color: COLORS.secondary }}>—</span>}
                          </td>
                          <td style={{ padding: "12px 16px", background: isSanBest ? "#f6ffed" : "transparent" }}>
                            {pr.san != null ? (
                              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                                <span style={{ fontWeight: 600, color: isSanBest ? COLORS.success : COLORS.heading }}>${pr.san.toFixed(2)}</span>
                                {isSanBest && <span style={{ fontSize: 9, background: COLORS.success, color: COLORS.white, padding: "1px 5px", borderRadius: 2, fontWeight: 600 }}>★ BEST</span>}
                              </div>
                            ) : <span style={{ color: COLORS.secondary }}>N/A</span>}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
                {/* Footer: WHMCS Sell Price & Best Margin (PDR §13.3) */}
                <div style={{ padding: "12px 16px", background: "#f5f7fa", borderTop: `1px solid ${COLORS.border}`, display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: 12 }}>
                  <div style={{ display: "flex", gap: 24 }}>
                    <span>
                      <span style={{ color: COLORS.secondary }}>WHMCS Sell Price: </span>
                      <strong style={{ color: COLORS.heading }}>${product.whmcsPrice.toFixed(2)}/yr</strong>
                    </span>
                    {bestMargin != null && (
                      <span>
                        <span style={{ color: COLORS.secondary }}>Best Margin: </span>
                        <strong style={{ color: COLORS.success }}>{PROVIDERS[best1.slug]?.name} (${bestMargin})</strong>
                      </span>
                    )}
                  </div>
                  <div style={{ display: "flex", gap: 6 }}>
                    <button style={{ padding: "4px 10px", background: "#e6f7ff", color: COLORS.primary, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>📋 Copy Prices</button>
                    <button style={{ padding: "4px 10px", background: "#f9f0ff", color: "#722ed1", border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>🔗 Edit Mapping</button>
                  </div>
                </div>
              </div>
            )}
          </div>
        );
      })}

      <div style={{ marginTop: 16, fontSize: 11, color: COLORS.secondary, display: "flex", gap: 16, flexWrap: "wrap" }}>
        <span>💡 Green cell + ★ BEST = Lowest reseller cost for that period</span>
        <span>📅 Prices auto-synced every 2 hours</span>
        <span>💲 All prices in USD (reseller cost, excl. tax)</span>
        <span>📊 Margin = WHMCS Sell Price − Best Reseller Cost</span>
      </div>
    </div>
  );
};

const OrdersPage = () => (
  <div>
    <div style={{ display: "flex", gap: 8, marginBottom: 16, flexWrap: "wrap", alignItems: "center" }}>
      <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
        <option>All Providers</option>
        {Object.entries(PROVIDERS).map(([k, v]) => <option key={k}>{v.name}</option>)}
      </select>
      <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
        <option>All Statuses</option><option>Pending</option><option>Processing</option><option>Issued</option><option>Expired</option><option>Awaiting Config</option>
      </select>
      <input type="text" placeholder="Search domain or client..." style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, width: 200 }} />
      <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
        <button style={{ padding: "7px 16px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔄 Bulk Refresh</button>
      </div>
    </div>
    <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
        <thead><tr style={{ background: "#fafafa" }}>
          <th style={{ padding: "10px 12px", width: 30 }}><input type="checkbox" /></th>
          {["Order ID", "Provider", "Domain", "Product", "Client", "Status", "Date", "Actions"].map(h => (
            <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
          ))}
        </tr></thead>
        <tbody>{ORDERS.map(o => (
          <tr key={o.id} style={{ borderBottom: "1px solid #f0f0f0" }}>
            <td style={{ padding: "10px 12px" }}><input type="checkbox" /></td>
            <td style={{ padding: "10px 12px" }}><a href="#" style={{ color: COLORS.primary, textDecoration: "none", fontWeight: 500 }}>{o.id}</a></td>
            <td style={{ padding: "10px 12px" }}><ProviderBadge slug={o.provider} /></td>
            <td style={{ padding: "10px 12px", fontFamily: "monospace", fontSize: 11 }}>{o.domain}</td>
            <td style={{ padding: "10px 12px" }}>{o.product}</td>
            <td style={{ padding: "10px 12px" }}>{o.client}</td>
            <td style={{ padding: "10px 12px" }}><StatusBadge status={o.status} /></td>
            <td style={{ padding: "10px 12px", color: COLORS.secondary }}>{o.date}</td>
            <td style={{ padding: "10px 12px" }}>
              <div style={{ display: "flex", gap: 4 }}>
                <button style={{ padding: "4px 8px", background: "#e6f7ff", color: COLORS.primary, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>View</button>
                <button style={{ padding: "4px 8px", background: "#f5f5f5", color: COLORS.text, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Refresh</button>
              </div>
            </td>
          </tr>
        ))}</tbody>
      </table>
    </div>
  </div>
);

// ── NEW: Import Page (PDR §13.1 — Legacy migration, API import, bulk import) ──
const ImportPage = () => {
  const [tab, setTab] = useState("migration");
  return (
    <div>
      <div style={{ display: "flex", gap: 0, marginBottom: 20, borderBottom: `1px solid ${COLORS.border}` }}>
        {[["migration", "🔄 Legacy Migration"], ["single", "📥 Single Import"], ["bulk", "📦 Bulk Import"], ["history", "📜 Import History"]].map(([k, l]) => (
          <button key={k} onClick={() => setTab(k)} style={{ padding: "10px 20px", background: "transparent", border: "none", borderBottom: `2px solid ${tab === k ? COLORS.primary : "transparent"}`, color: tab === k ? COLORS.primary : COLORS.text, fontSize: 13, fontWeight: tab === k ? 600 : 400, cursor: "pointer" }}>{l}</button>
        ))}
      </div>

      {tab === "migration" && (
        <div>
          <div style={{ background: "#e6f7ff", border: "1px solid #91d5ff", borderRadius: 6, padding: 16, marginBottom: 20, fontSize: 12, color: COLORS.heading }}>
            <strong>ℹ️ Legacy Module Migration</strong> — Detect and claim existing SSL orders from legacy modules (NicSRS, GoGetSSL, TheSSLStore, SSL2Buy) into the AIO SSL Manager. This updates <code>tblsslorders.module</code> to <code>aio_ssl</code> and normalizes configdata.
          </div>
          <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
            {LEGACY_MODULES.map(m => (
              <div key={m.module} style={{ flex: 1, minWidth: 220, background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
                  <span style={{ fontWeight: 600, fontSize: 13, color: COLORS.heading }}>{m.name}</span>
                  <StatusBadge status={m.status} />
                </div>
                <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 4 }}>Module: <code style={{ fontSize: 11 }}>{m.module}</code></div>
                <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 12 }}>
                  <span>Total Orders: <strong>{m.orders}</strong></span>
                  <span>Claimable: <strong style={{ color: COLORS.primary }}>{m.claimable}</strong></span>
                </div>
                <div style={{ display: "flex", gap: 8 }}>
                  <button style={{ flex: 1, padding: "7px 0", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer" }}>Claim All</button>
                  <button style={{ padding: "7px 12px", background: "#f5f5f5", color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>Preview</button>
                </div>
              </div>
            ))}
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
            <div style={{ fontSize: 13, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>Migration Summary</div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 16, fontSize: 12 }}>
              <div style={{ textAlign: "center" }}><div style={{ fontSize: 24, fontWeight: 700, color: COLORS.primary }}>847</div><div style={{ color: COLORS.secondary }}>Total Legacy Orders</div></div>
              <div style={{ textAlign: "center" }}><div style={{ fontSize: 24, fontWeight: 700, color: COLORS.success }}>0</div><div style={{ color: COLORS.secondary }}>Already Claimed</div></div>
              <div style={{ textAlign: "center" }}><div style={{ fontSize: 24, fontWeight: 700, color: COLORS.warning }}>847</div><div style={{ color: COLORS.secondary }}>Pending Claim</div></div>
              <div style={{ textAlign: "center" }}><div style={{ fontSize: 24, fontWeight: 700, color: COLORS.danger }}>0</div><div style={{ color: COLORS.secondary }}>Errors</div></div>
            </div>
          </div>
        </div>
      )}

      {tab === "single" && (
        <div style={{ maxWidth: 600 }}>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
            <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>📥 Import Single Certificate</div>
            <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 16 }}>Enter a provider and remote order ID to fetch certificate data from the provider API and create a <code>tblsslorders</code> record.</div>
            <div style={{ marginBottom: 12 }}>
              <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Provider</label>
              <select style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }}>
                <option>— Select Provider —</option>
                {Object.entries(PROVIDERS).map(([k, v]) => <option key={k}>{v.name}</option>)}
              </select>
            </div>
            <div style={{ marginBottom: 12 }}>
              <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Remote Order ID</label>
              <input placeholder="e.g. 78542" style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
            </div>
            <div style={{ marginBottom: 16 }}>
              <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Link to WHMCS Service (optional)</label>
              <input placeholder="Service ID or search client..." style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
            </div>
            <button style={{ padding: "10px 24px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 13, fontWeight: 600, cursor: "pointer" }}>🔍 Fetch & Import</button>
          </div>
        </div>
      )}

      {tab === "bulk" && (
        <div style={{ maxWidth: 700 }}>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
            <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>📦 Bulk Import via CSV</div>
            <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 16 }}>Upload a CSV file with columns: <code>provider, remote_id, service_id (optional)</code>. Each row will be fetched from the provider API and imported.</div>
            <div style={{ border: `2px dashed ${COLORS.border}`, borderRadius: 6, padding: 32, textAlign: "center", marginBottom: 16, background: "#fafafa" }}>
              <div style={{ fontSize: 32, marginBottom: 8 }}>📄</div>
              <div style={{ fontSize: 13, color: COLORS.heading, marginBottom: 4 }}>Drop CSV file here or click to browse</div>
              <div style={{ fontSize: 11, color: COLORS.secondary }}>Accepted format: .csv — Max 500 rows per batch</div>
            </div>
            <div style={{ display: "flex", gap: 8 }}>
              <button style={{ padding: "10px 24px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 13, fontWeight: 600, cursor: "pointer" }}>📤 Upload & Process</button>
              <button style={{ padding: "10px 20px", background: COLORS.white, color: COLORS.primary, border: `1px solid ${COLORS.primary}`, borderRadius: 4, fontSize: 13, cursor: "pointer" }}>📋 Download Template</button>
            </div>
          </div>
        </div>
      )}

      {tab === "history" && (
        <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
            <thead><tr style={{ background: "#fafafa" }}>
              {["Import ID", "Type", "Source", "Records", "Status", "Date"].map(h => (
                <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>{IMPORT_HISTORY.map(r => (
              <tr key={r.id} style={{ borderBottom: "1px solid #f0f0f0" }}>
                <td style={{ padding: "10px 12px", fontWeight: 500, color: COLORS.primary }}>{r.id}</td>
                <td style={{ padding: "10px 12px" }}><Badge color={r.type === "Migration" ? "#722ed1" : r.type === "API Import" ? COLORS.primary : COLORS.warning} bg={r.type === "Migration" ? "#f9f0ff" : r.type === "API Import" ? "#e6f7ff" : "#fffbe6"}>{r.type}</Badge></td>
                <td style={{ padding: "10px 12px" }}>{r.source}</td>
                <td style={{ padding: "10px 12px", fontWeight: 600 }}>{r.count}</td>
                <td style={{ padding: "10px 12px" }}><StatusBadge status={r.status} /></td>
                <td style={{ padding: "10px 12px", color: COLORS.secondary }}>{r.date}</td>
              </tr>
            ))}</tbody>
          </table>
        </div>
      )}
    </div>
  );
};

// ── NEW: Reports Page (PDR §13.1 — Revenue, performance, expiry forecast) ──
const ReportsPage = () => {
  const [tab, setTab] = useState("revenue");
  const maxRev = Math.max(...REVENUE_BY_PROVIDER.map(r => r.revenue));
  const maxOrd = Math.max(...TOP_PRODUCTS.map(r => r.orders));
  return (
    <div>
      <div style={{ display: "flex", gap: 0, marginBottom: 20, borderBottom: `1px solid ${COLORS.border}` }}>
        {[["revenue", "💰 Revenue by Provider"], ["performance", "🏆 Product Performance"], ["expiry", "⏰ Expiry Forecast"]].map(([k, l]) => (
          <button key={k} onClick={() => setTab(k)} style={{ padding: "10px 20px", background: "transparent", border: "none", borderBottom: `2px solid ${tab === k ? COLORS.primary : "transparent"}`, color: tab === k ? COLORS.primary : COLORS.text, fontSize: 13, fontWeight: tab === k ? 600 : 400, cursor: "pointer" }}>{l}</button>
        ))}
        <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8 }}>
          <button style={{ padding: "7px 16px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>📥 Export CSV</button>
        </div>
      </div>

      {tab === "revenue" && (
        <div>
          <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
            {[
              { label: "Total Revenue", value: "$82,050", icon: "💰", color: COLORS.primary },
              { label: "Total Cost", value: "$40,290", icon: "💸", color: COLORS.warning },
              { label: "Total Profit", value: "$41,760", icon: "📈", color: COLORS.success },
              { label: "Avg Margin", value: "50.9%", icon: "📊", color: "#722ed1" },
            ].map(s => (
              <div key={s.label} style={{ flex: 1, minWidth: 180, background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: "16px 20px", borderTop: `3px solid ${s.color}` }}>
                <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 4 }}>{s.label}</div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                  <div style={{ fontSize: 24, fontWeight: 700, color: COLORS.heading }}>{s.value}</div>
                  <span style={{ fontSize: 20 }}>{s.icon}</span>
                </div>
              </div>
            ))}
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>Revenue & Profit by Provider</div>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
              <thead><tr style={{ background: "#fafafa" }}>
                {["Provider", "Orders", "Revenue", "Cost", "Profit", "Margin", "Revenue Share"].map(h => (
                  <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
                ))}
              </tr></thead>
              <tbody>{REVENUE_BY_PROVIDER.map(r => (
                <tr key={r.provider} style={{ borderBottom: "1px solid #f0f0f0" }}>
                  <td style={{ padding: "10px 12px" }}><ProviderBadge slug={r.provider} /></td>
                  <td style={{ padding: "10px 12px", fontWeight: 600 }}>{r.orders}</td>
                  <td style={{ padding: "10px 12px", fontWeight: 600, color: COLORS.heading }}>${r.revenue.toLocaleString()}</td>
                  <td style={{ padding: "10px 12px", color: COLORS.secondary }}>${r.cost.toLocaleString()}</td>
                  <td style={{ padding: "10px 12px", fontWeight: 600, color: COLORS.success }}>${r.profit.toLocaleString()}</td>
                  <td style={{ padding: "10px 12px" }}><Badge color={r.margin > 60 ? COLORS.success : r.margin > 40 ? COLORS.warning : COLORS.danger} bg={r.margin > 60 ? "#f6ffed" : r.margin > 40 ? "#fffbe6" : "#fff1f0"}>{r.margin}%</Badge></td>
                  <td style={{ padding: "10px 12px", width: 180 }}><MiniBar value={r.revenue} max={maxRev} color={PROVIDERS[r.provider]?.color} /></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        </div>
      )}

      {tab === "performance" && (
        <div>
          <div style={{ display: "flex", gap: 8, marginBottom: 16 }}>
            <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
              <option>Last 30 days</option><option>Last 90 days</option><option>Last 6 months</option><option>Last year</option>
            </select>
            <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
              <option>All Vendors</option><option>Sectigo</option><option>DigiCert</option><option>GlobalSign</option>
            </select>
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>🏆 Top Selling Products</div>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
              <thead><tr style={{ background: "#fafafa" }}>
                {["#", "Product", "Orders", "Revenue", "Share", ""].map(h => (
                  <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
                ))}
              </tr></thead>
              <tbody>{TOP_PRODUCTS.map((p, i) => (
                <tr key={i} style={{ borderBottom: "1px solid #f0f0f0" }}>
                  <td style={{ padding: "10px 12px", fontWeight: 700, color: i < 3 ? COLORS.primary : COLORS.secondary }}>{i + 1}</td>
                  <td style={{ padding: "10px 12px", fontWeight: 500 }}>{p.name}</td>
                  <td style={{ padding: "10px 12px", fontWeight: 600 }}>{p.orders}</td>
                  <td style={{ padding: "10px 12px", fontWeight: 600, color: COLORS.heading }}>${p.revenue.toLocaleString()}</td>
                  <td style={{ padding: "10px 12px" }}><Badge color={COLORS.primary} bg="#e6f7ff">{p.pct}%</Badge></td>
                  <td style={{ padding: "10px 12px", width: 160 }}><MiniBar value={p.orders} max={maxOrd} color={COLORS.primary} /></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        </div>
      )}

      {tab === "expiry" && (
        <div>
          <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
            {EXPIRY_FORECAST.map((e, i) => (
              <div key={i} style={{ flex: 1, minWidth: 200, background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16, borderLeft: `4px solid ${i === 0 ? COLORS.danger : i === 1 ? COLORS.warning : COLORS.primary}` }}>
                <div style={{ fontSize: 12, color: COLORS.secondary, marginBottom: 4 }}>{e.period}</div>
                <div style={{ fontSize: 28, fontWeight: 700, color: i === 0 ? COLORS.danger : i === 1 ? COLORS.warning : COLORS.heading }}>{e.count}</div>
                <div style={{ fontSize: 11, color: COLORS.secondary, marginTop: 8 }}>certificates expiring</div>
              </div>
            ))}
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>⏰ Certificates Expiring Within 30 Days</div>
            <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
              <thead><tr style={{ background: "#fafafa" }}>
                {["Domain", "Product", "Provider", "Expiry Date", "Days Left", "Action"].map(h => (
                  <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
                ))}
              </tr></thead>
              <tbody>
                {[
                  { domain: "api.hvn.vn", product: "Sectigo PositiveSSL", provider: "nicsrs", expiry: "2026-02-14", days: 3 },
                  { domain: "shop.vn", product: "Sectigo EV SSL", provider: "gogetssl", expiry: "2026-02-23", days: 12 },
                  { domain: "mail.example.com", product: "DigiCert Basic", provider: "thesslstore", expiry: "2026-03-02", days: 19 },
                  { domain: "secure.io", product: "Comodo PositiveSSL", provider: "ssl2buy", expiry: "2026-03-08", days: 25 },
                  { domain: "cdn.hvn.vn", product: "Sectigo Essential Wildcard", provider: "nicsrs", expiry: "2026-03-11", days: 28 },
                ].map((c, i) => (
                  <tr key={i} style={{ borderBottom: "1px solid #f0f0f0" }}>
                    <td style={{ padding: "10px 12px", fontFamily: "monospace", fontSize: 11 }}>{c.domain}</td>
                    <td style={{ padding: "10px 12px" }}>{c.product}</td>
                    <td style={{ padding: "10px 12px" }}><ProviderBadge slug={c.provider} /></td>
                    <td style={{ padding: "10px 12px", color: COLORS.secondary }}>{c.expiry}</td>
                    <td style={{ padding: "10px 12px" }}>
                      <Badge color={c.days <= 7 ? COLORS.danger : c.days <= 14 ? COLORS.warning : COLORS.primary} bg={c.days <= 7 ? "#fff1f0" : c.days <= 14 ? "#fffbe6" : "#e6f7ff"}>
                        {c.days} days
                      </Badge>
                    </td>
                    <td style={{ padding: "10px 12px" }}>
                      <button style={{ padding: "4px 10px", background: "#f6ffed", color: COLORS.success, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Renew</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

const OrderDetailPage = () => {
  const o = ORDERS[0];
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
        <button style={{ padding: "4px 10px", background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>← Back</button>
        <span style={{ fontSize: 16, fontWeight: 600, color: COLORS.heading }}>Order {o.id}</span>
        <StatusBadge status={o.status} />
        <ProviderBadge slug={o.provider} />
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 16 }}>
        <div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, marginBottom: 16 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>🔒 Certificate Details</div>
            <div style={{ padding: 16, display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px 24px", fontSize: 12 }}>
              {[["Domain", "hvn.vn"], ["Product", "Sectigo PositiveSSL"], ["Provider", "NicSRS"], ["Validation", "DV"], ["Remote ID", "NICSRS-78542"], ["Status", "Issued"], ["Start Date", "2026-01-15"], ["End Date", "2027-01-15"], ["DCV Method", "DNS CNAME"], ["Approver Email", "—"]].map(([k, v]) => (
                <div key={k}><div style={{ color: COLORS.secondary, fontSize: 11, textTransform: "uppercase", letterSpacing: 0.5 }}>{k}</div><div style={{ fontWeight: 500, color: COLORS.heading, marginTop: 2 }}>{v}</div></div>
              ))}
            </div>
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>📜 Certificate Data</div>
            <div style={{ padding: 16 }}>
              <div style={{ background: "#f5f5f5", borderRadius: 4, padding: 12, fontFamily: "monospace", fontSize: 11, color: COLORS.text, maxHeight: 120, overflow: "auto", whiteSpace: "pre" }}>
{`-----BEGIN CERTIFICATE-----
MIIFjTCCBHWgAwIBAgIQDHvHMX...
(certificate data truncated)
-----END CERTIFICATE-----`}
              </div>
            </div>
          </div>
        </div>
        <div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, marginBottom: 16 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>⚡ Actions</div>
            <div style={{ padding: 12, display: "flex", flexDirection: "column", gap: 8 }}>
              {[["🔄 Refresh Status", COLORS.primary, "#e6f7ff"], ["📥 Download Certificate", COLORS.success, "#f6ffed"], ["🔁 Reissue Certificate", "#722ed1", "#f9f0ff"], ["📧 Resend DCV Email", COLORS.warning, "#fffbe6"], ["🔐 Revoke Certificate", COLORS.danger, "#fff1f0"]].map(([l, c, bg]) => (
                <button key={l} style={{ padding: "10px 16px", background: bg, color: c, border: `1px solid ${c}22`, borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer", textAlign: "left" }}>{l}</button>
              ))}
            </div>
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>📋 Activity Log</div>
            <div style={{ padding: 12 }}>
              {[["Certificate issued", "2026-01-15 10:30", COLORS.success], ["DCV validated (DNS)", "2026-01-15 10:28", COLORS.primary], ["CSR submitted", "2026-01-15 09:45", COLORS.text], ["Order created", "2026-01-15 09:30", COLORS.secondary]].map(([l, d, c], i) => (
                <div key={i} style={{ display: "flex", justifyContent: "space-between", padding: "8px 0", borderBottom: i < 3 ? "1px solid #f0f0f0" : "none", fontSize: 12 }}>
                  <span style={{ color: c }}>{l}</span>
                  <span style={{ color: COLORS.secondary, fontSize: 11 }}>{d}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

const Tier2OrderDetail = () => (
  <div>
    <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
      <button style={{ padding: "4px 10px", background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>← Back</button>
      <span style={{ fontSize: 16, fontWeight: 600, color: COLORS.heading }}>Tier 2 Order SSL2B-99234</span>
      <Badge color="#fa8c16" bg="#fff7e6">Awaiting Config</Badge>
      <Badge color="#fa8c16" bg={`#fa8c1612`}>SSL2Buy <span style={{ marginLeft: 4, fontSize: 9, opacity: 0.7 }}>T2</span></Badge>
    </div>
    <div style={{ background: "#fffbe6", border: "1px solid #ffe58f", borderRadius: 6, padding: 16, marginBottom: 16 }}>
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 8, fontSize: 13, fontWeight: 600, color: "#d48806" }}>
        ⚠️ Limited Tier Provider — Manual Configuration Required
      </div>
      <div style={{ fontSize: 12, color: COLORS.text, marginBottom: 12 }}>
        SSL2Buy has limited API support. Use the link and PIN below to configure, validate, reissue, or download your certificate.
      </div>
      <div style={{ display: "flex", gap: 16, flexWrap: "wrap" }}>
        <div style={{ background: COLORS.white, borderRadius: 4, padding: "10px 16px", border: `1px solid ${COLORS.border}` }}>
          <div style={{ fontSize: 10, color: COLORS.secondary, textTransform: "uppercase", marginBottom: 2 }}>Configuration Link</div>
          <a href="#" style={{ color: COLORS.primary, fontSize: 13, fontWeight: 500 }}>https://ssl2buy.com/configure/abc123...</a>
        </div>
        <div style={{ background: COLORS.white, borderRadius: 4, padding: "10px 16px", border: `1px solid ${COLORS.border}` }}>
          <div style={{ fontSize: 10, color: COLORS.secondary, textTransform: "uppercase", marginBottom: 2 }}>PIN Code</div>
          <code style={{ fontSize: 16, fontWeight: 700, color: COLORS.heading, letterSpacing: 2 }}>XK92-M7PQ</code>
        </div>
      </div>
      <button style={{ marginTop: 12, padding: "8px 20px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 13, fontWeight: 500, cursor: "pointer" }}>🔗 Open Configuration Portal</button>
    </div>
    <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 16 }}>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>🔒 Order Details</div>
        <div style={{ padding: 16, display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px 24px", fontSize: 12 }}>
          {[["Domain", "secure.io"], ["Product", "Comodo PositiveSSL"], ["Provider", "SSL2Buy (Tier 2)"], ["Brand", "Comodo"], ["Order ID", "SSL2B-99234"], ["Status", "Awaiting Configuration"], ["Created", "2026-02-09"], ["Period", "1 Year"]].map(([k, v]) => (
            <div key={k}><div style={{ color: COLORS.secondary, fontSize: 11, textTransform: "uppercase", letterSpacing: 0.5 }}>{k}</div><div style={{ fontWeight: 500, color: COLORS.heading, marginTop: 2 }}>{v}</div></div>
          ))}
        </div>
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>⚡ Available Actions</div>
        <div style={{ padding: 12, display: "flex", flexDirection: "column", gap: 8 }}>
          <button style={{ padding: "10px 16px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}22`, borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer", textAlign: "left" }}>🔄 Refresh Status</button>
          <button style={{ padding: "10px 16px", background: "#fffbe6", color: "#d48806", border: "1px solid #d4880622", borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer", textAlign: "left" }}>📧 Resend Approval Email</button>
          <div style={{ borderTop: `1px solid ${COLORS.border}`, paddingTop: 8, marginTop: 4, fontSize: 11, color: COLORS.secondary }}>
            <div style={{ marginBottom: 4 }}>❌ Reissue — Use config portal</div>
            <div style={{ marginBottom: 4 }}>❌ Revoke — Not supported via API</div>
            <div>❌ Download — Use config portal</div>
          </div>
        </div>
      </div>
    </div>
  </div>
);

const SettingsPage = () => {
  const [tab, setTab] = useState("general");
  return (
    <div>
      <div style={{ display: "flex", gap: 0, marginBottom: 20, borderBottom: `1px solid ${COLORS.border}` }}>
        {[["general", "General"], ["sync", "Auto-Sync"], ["notifications", "Notifications"], ["currency", "Currency"], ["security", "Security"]].map(([k, l]) => (
          <button key={k} onClick={() => setTab(k)} style={{ padding: "10px 20px", background: "transparent", border: "none", borderBottom: `2px solid ${tab === k ? COLORS.primary : "transparent"}`, color: tab === k ? COLORS.primary : COLORS.text, fontSize: 13, fontWeight: tab === k ? 600 : 400, cursor: "pointer" }}>{l}</button>
        ))}
      </div>
      {tab === "general" && (
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
            <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Display Settings</div>
            {[["Items Per Page", "20"], ["Date Format", "Y-m-d"], ["Default Currency", "USD"]].map(([l, v]) => (
              <div key={l} style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>{l}</label>
                <input defaultValue={v} style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
              </div>
            ))}
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
            <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Admin Notifications</div>
            {[["Admin Email", "admin@hvn.vn"]].map(([l, v]) => (
              <div key={l} style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>{l}</label>
                <input defaultValue={v} style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
              </div>
            ))}
            {[["Email on certificate issuance", true], ["Email on certificate expiry", true], ["Email on sync errors", false]].map(([l, c]) => (
              <label key={l} style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 12, color: COLORS.text, marginBottom: 8 }}>
                <input type="checkbox" defaultChecked={c} /> {l}
              </label>
            ))}
          </div>
        </div>
      )}
      {tab === "sync" && (
        <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Auto-Sync Configuration</div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
            <div>
              <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 12, color: COLORS.text, marginBottom: 12 }}>
                <input type="checkbox" defaultChecked /> Enable Auto-Sync
              </label>
              {[["Sync Interval (minutes)", "30"], ["Batch Size", "50"], ["Error Threshold", "3"], ["Expiry Warning Days", "30"]].map(([l, v]) => (
                <div key={l} style={{ marginBottom: 12 }}>
                  <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>{l}</label>
                  <input defaultValue={v} type="number" style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
                </div>
              ))}
            </div>
            <div style={{ background: "#f5f5f5", borderRadius: 6, padding: 16 }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>Sync Status</div>
              {[["Last Sync", "2026-02-11 10:45:00"], ["Next Sync", "2026-02-11 11:15:00"], ["Total Synced", "847 orders"], ["Errors (24h)", "0"]].map(([k, v]) => (
                <div key={k} style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 8 }}>
                  <span style={{ color: COLORS.secondary }}>{k}</span>
                  <span style={{ fontWeight: 500, color: COLORS.heading }}>{v}</span>
                </div>
              ))}
              <button style={{ marginTop: 8, width: "100%", padding: "8px 0", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔄 Force Sync Now</button>
            </div>
          </div>
        </div>
      )}
      {tab === "notifications" && (
        <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Notification Templates</div>
          {[["Certificate Issued", "Sent to client when certificate is issued", true], ["Expiry Warning (30d)", "Sent 30 days before certificate expiry", true], ["Expiry Warning (7d)", "Sent 7 days before certificate expiry", true], ["Sync Error Alert", "Admin notification on sync failures", false], ["Price Change Alert", "Admin notification when provider prices change", true]].map(([n, d, on]) => (
            <div key={n} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 0", borderBottom: `1px solid ${COLORS.border}` }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: COLORS.heading }}>{n}</div>
                <div style={{ fontSize: 11, color: COLORS.secondary }}>{d}</div>
              </div>
              <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <Badge color={on ? COLORS.success : COLORS.secondary} bg={on ? "#f6ffed" : COLORS.bg}>{on ? "Active" : "Disabled"}</Badge>
                <button style={{ padding: "4px 10px", background: "#f5f5f5", border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>Edit</button>
              </div>
            </div>
          ))}
        </div>
      )}
      {tab === "currency" && (
        <div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
            <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>💱 Currency Configuration</div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Base Currency (Provider APIs)</label>
                <select style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }}>
                  <option>USD — US Dollar</option><option>EUR — Euro</option><option>GBP — British Pound</option>
                </select>
                <div style={{ fontSize: 11, color: COLORS.secondary, marginTop: 4 }}>All provider API prices are in this currency</div>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Display Currency</label>
                <select style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }}>
                  <option>VND — Vietnamese Dong</option><option>USD — US Dollar</option><option>EUR — Euro</option>
                </select>
                <div style={{ fontSize: 11, color: COLORS.secondary, marginTop: 4 }}>Display prices in admin panel in this currency</div>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Number Format</label>
                <div style={{ display: "flex", gap: 8 }}>
                  <select style={{ flex: 1, padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13 }}>
                    <option>1,234.56 (US)</option><option>1.234,56 (EU)</option><option>1 234.56 (FR)</option>
                  </select>
                  <input defaultValue="2" type="number" min="0" max="4" style={{ width: 80, padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, textAlign: "center" }} />
                  <span style={{ alignSelf: "center", fontSize: 11, color: COLORS.secondary }}>decimals</span>
                </div>
              </div>
            </div>
            <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
              <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>🔄 Exchange Rate — Auto Update</div>
              <div style={{ background: "#e6f7ff", border: "1px solid #91d5ff", borderRadius: 6, padding: 12, marginBottom: 16, fontSize: 12 }}>
                <strong>API Source:</strong> <a href="https://exchangerate-api.com" style={{ color: COLORS.primary }}>exchangerate-api.com</a>
                <div style={{ fontSize: 11, color: COLORS.secondary, marginTop: 4 }}>Free tier: 1,500 requests/month — sufficient for daily updates</div>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>API Key</label>
                <div style={{ display: "flex", gap: 8 }}>
                  <input type="password" defaultValue="your-api-key-here" style={{ flex: 1, padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} />
                  <button style={{ padding: "8px 14px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer", whiteSpace: "nowrap" }}>🔗 Get Key</button>
                </div>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 12, color: COLORS.text }}>
                  <input type="checkbox" defaultChecked /> Enable auto-update exchange rates
                </label>
              </div>
              <div style={{ marginBottom: 12 }}>
                <label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Update Frequency</label>
                <select style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }}>
                  <option>Every 6 hours</option><option>Every 12 hours</option><option>Daily</option><option>Weekly</option>
                </select>
              </div>
              <button style={{ width: "100%", padding: "10px 0", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, fontWeight: 600, cursor: "pointer", marginTop: 4 }}>🔄 Fetch Rates Now</button>
            </div>
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
              <div style={{ fontSize: 13, fontWeight: 600, color: COLORS.heading }}>📊 Current Exchange Rates (Base: USD)</div>
              <div style={{ fontSize: 11, color: COLORS.secondary }}>Last updated: 2026-02-12 08:30 UTC</div>
            </div>
            <div style={{ padding: 16 }}>
              <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
                <thead><tr style={{ background: "#fafafa" }}>
                  {["Currency", "Code", "Rate (1 USD =)", "Inverse (1 unit = USD)", "Status", "Action"].map(h => (
                    <th key={h} style={{ padding: "8px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
                  ))}
                </tr></thead>
                <tbody>
                  {[
                    { name: "Vietnamese Dong", code: "VND", rate: 25385.00, status: "active" },
                    { name: "Euro", code: "EUR", rate: 0.9234, status: "active" },
                    { name: "British Pound", code: "GBP", rate: 0.7912, status: "active" },
                    { name: "Japanese Yen", code: "JPY", rate: 152.45, status: "inactive" },
                    { name: "Chinese Yuan", code: "CNY", rate: 7.2480, status: "inactive" },
                  ].map(c => (
                    <tr key={c.code} style={{ borderBottom: "1px solid #f0f0f0" }}>
                      <td style={{ padding: "8px 12px" }}>{c.name}</td>
                      <td style={{ padding: "8px 12px", fontWeight: 600, fontFamily: "monospace" }}>{c.code}</td>
                      <td style={{ padding: "8px 12px", fontWeight: 600, color: COLORS.heading }}>{c.rate.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}</td>
                      <td style={{ padding: "8px 12px", color: COLORS.secondary }}>{(1 / c.rate).toFixed(6)}</td>
                      <td style={{ padding: "8px 12px" }}><Badge color={c.status === "active" ? COLORS.success : COLORS.secondary} bg={c.status === "active" ? "#f6ffed" : COLORS.bg}>{c.status === "active" ? "Active" : "Inactive"}</Badge></td>
                      <td style={{ padding: "8px 12px" }}>
                        <button style={{ padding: "4px 8px", background: c.status === "active" ? "#fff1f0" : "#f6ffed", color: c.status === "active" ? COLORS.danger : COLORS.success, border: "none", borderRadius: 3, fontSize: 11, cursor: "pointer" }}>
                          {c.status === "active" ? "Disable" : "Enable"}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
      {tab === "security" && (
        <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 20 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Security Settings</div>
          <div style={{ background: "#f6ffed", border: "1px solid #b7eb8f", borderRadius: 6, padding: 16, marginBottom: 16, fontSize: 12 }}>
            <strong>✅ Encryption Active</strong> — API credentials are encrypted using AES-256-CBC with WHMCS <code>cc_encryption_hash</code> as the key.
          </div>
          {[["Re-encrypt all credentials", "Force re-encryption of all stored API credentials"], ["Audit API access logs", "View recent API calls and authentication events"], ["Test all connections", "Verify all provider API connections are working"]].map(([t, d]) => (
            <div key={t} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 0", borderBottom: `1px solid ${COLORS.border}` }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: COLORS.heading }}>{t}</div>
                <div style={{ fontSize: 11, color: COLORS.secondary }}>{d}</div>
              </div>
              <button style={{ padding: "6px 14px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>Run</button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

const ClientConfigPage = () => (
  <div>
    <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
      <span style={{ fontSize: 16, fontWeight: 600, color: COLORS.heading }}>🖥️ Client Area — SSL Configuration</span>
      <Badge color={COLORS.primary} bg="#e6f7ff">Step 2 of 4</Badge>
    </div>
    <div style={{ display: "flex", gap: 8, marginBottom: 20 }}>
      {[["1", "Apply", true], ["2", "CSR & DCV", true], ["3", "Validation", false], ["4", "Complete", false]].map(([n, l, active], i) => (
        <div key={n} style={{ display: "flex", alignItems: "center", gap: 8, flex: 1 }}>
          <div style={{ width: 28, height: 28, borderRadius: "50%", background: active ? COLORS.primary : COLORS.bg, color: active ? COLORS.white : COLORS.secondary, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 600, flexShrink: 0 }}>{n}</div>
          <span style={{ fontSize: 12, fontWeight: active ? 600 : 400, color: active ? COLORS.heading : COLORS.secondary }}>{l}</span>
          {i < 3 && <div style={{ flex: 1, height: 1, background: COLORS.border, marginLeft: 8 }} />}
        </div>
      ))}
    </div>
    <div style={{ padding: 24 }}>
      <div style={{ marginBottom: 20 }}>
        <label style={{ fontSize: 13, fontWeight: 600, color: COLORS.heading, display: "block", marginBottom: 8 }}>Certificate Signing Request (CSR)</label>
        <textarea placeholder={"-----BEGIN CERTIFICATE REQUEST-----\nPaste your CSR here...\n-----END CERTIFICATE REQUEST-----"} style={{ width: "100%", minHeight: 120, padding: 12, border: `1px solid ${COLORS.border}`, borderRadius: 6, fontFamily: "monospace", fontSize: 12, resize: "vertical", boxSizing: "border-box" }} />
        <div style={{ display: "flex", gap: 8, marginTop: 8 }}>
          <button style={{ padding: "6px 14px", background: "#e6f7ff", color: COLORS.primary, border: `1px solid ${COLORS.primary}33`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔑 Generate CSR</button>
          <button style={{ padding: "6px 14px", background: "#f5f5f5", color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>📋 Decode CSR</button>
        </div>
      </div>
      <div style={{ marginBottom: 20 }}>
        <label style={{ fontSize: 13, fontWeight: 600, color: COLORS.heading, display: "block", marginBottom: 8 }}>Domain Control Validation (DCV) Method</label>
        <div style={{ display: "flex", gap: 12 }}>
          {[["📧 Email", false], ["🌐 HTTP File", false], ["📝 DNS CNAME", true]].map(([l, sel]) => (
            <button key={l} style={{ padding: "10px 20px", background: sel ? "#e6f7ff" : COLORS.white, color: sel ? COLORS.primary : COLORS.text, border: `1px solid ${sel ? COLORS.primary : COLORS.border}`, borderRadius: 6, fontSize: 12, fontWeight: sel ? 600 : 400, cursor: "pointer" }}>{l}</button>
          ))}
        </div>
      </div>
      <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
        <button style={{ padding: "10px 20px", background: COLORS.white, color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 6, fontSize: 13, cursor: "pointer" }}>💾 Save Draft</button>
        <button style={{ padding: "10px 24px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 6, fontSize: 13, fontWeight: 600, cursor: "pointer" }}>Next Step →</button>
      </div>
    </div>
  </div>
);

const PAGES = {
  dashboard: { label: "Dashboard", icon: "📊", component: DashboardPage },
  providers: { label: "Providers", icon: "🔌", component: ProvidersPage },
  products: { label: "Products", icon: "📦", component: ProductsPage },
  compare: { label: "Compare", icon: "⚖️", component: ComparePage },
  orders: { label: "Orders", icon: "📋", component: OrdersPage },
  import: { label: "Import", icon: "📥", component: ImportPage },
  reports: { label: "Reports", icon: "📊", component: ReportsPage },
  order_detail: { label: "Order Detail", icon: "🔒", component: OrderDetailPage },
  tier2_detail: { label: "Tier 2 Order", icon: "🔗", component: Tier2OrderDetail },
  settings: { label: "Settings", icon: "⚙️", component: SettingsPage },
  client_config: { label: "Client: Configure", icon: "🖥️", component: ClientConfigPage },
};

export default function AIOSSLMockup() {
  const [page, setPage] = useState("dashboard");
  const Pg = PAGES[page]?.component || DashboardPage;
  const mainTabs = ["dashboard", "providers", "products", "compare", "orders", "import", "reports", "settings"];
  const extraPages = ["order_detail", "tier2_detail", "client_config"];

  return (
    <div style={{ fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif", background: "#f0f2f5", minHeight: "100vh", padding: 16 }}>
      <div style={{ maxWidth: 1200, margin: "0 auto" }}>
        <div style={{ background: COLORS.white, borderRadius: "8px 8px 0 0", padding: "16px 24px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", alignItems: "center", justifyContent: "space-between" }}>
          <div>
            <div style={{ fontSize: 20, fontWeight: 700, color: COLORS.heading }}>
              <span style={{ color: COLORS.primary }}>🛡️ HVN</span> — AIO SSL Manager
            </div>
            <div style={{ fontSize: 11, color: COLORS.secondary }}>Centralized SSL Certificate Management for WHMCS • v1.0.0</div>
          </div>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            {Object.entries(PROVIDERS).map(([k, v]) => (
              <div key={k} style={{ display: "flex", alignItems: "center", gap: 4 }}>
                <div style={{ width: 6, height: 6, borderRadius: "50%", background: COLORS.success }} />
                <span style={{ fontSize: 10, color: COLORS.secondary }}>{v.name}</span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ background: COLORS.white, borderBottom: `1px solid ${COLORS.border}`, display: "flex", flexWrap: "wrap", padding: "0 24px" }}>
          {mainTabs.map(k => (
            <button key={k} onClick={() => setPage(k)} style={{ padding: "12px 16px", background: "transparent", border: "none", borderBottom: `2px solid ${page === k ? COLORS.primary : "transparent"}`, color: page === k ? COLORS.primary : COLORS.text, fontSize: 13, fontWeight: page === k ? 600 : 400, cursor: "pointer", display: "flex", alignItems: "center", gap: 4 }}>
              <span>{PAGES[k].icon}</span> {PAGES[k].label}
            </button>
          ))}
          <div style={{ borderLeft: `1px solid ${COLORS.border}`, margin: "8px 8px", height: "auto" }} />
          <div style={{ display: "flex", alignItems: "center", gap: 4, fontSize: 11, color: COLORS.secondary, padding: "0 8px" }}>Demo pages:</div>
          {extraPages.map(k => (
            <button key={k} onClick={() => setPage(k)} style={{ padding: "12px 10px", background: "transparent", border: "none", borderBottom: `2px solid ${page === k ? "#722ed1" : "transparent"}`, color: page === k ? "#722ed1" : COLORS.secondary, fontSize: 11, cursor: "pointer" }}>
              {PAGES[k].icon} {PAGES[k].label}
            </button>
          ))}
        </div>

        <div style={{ background: COLORS.white, borderRadius: "0 0 8px 8px", padding: 24, minHeight: 500 }}>
          <Pg />
        </div>

        <div style={{ textAlign: "center", padding: "12px 0", fontSize: 11, color: COLORS.secondary }}>
          HVN - AIO SSL Manager v1.0.0 • Powered by <a href="https://hvn.vn" style={{ color: COLORS.primary, textDecoration: "none" }}>HVN GROUP</a> • Design Mockup
        </div>
      </div>
    </div>
  );
}