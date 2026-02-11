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

const PRODUCTS_COMPARE = [
  { type: "DV SSL", nicsrs: { name: "Sectigo PositiveSSL", p1: 5.99, p2: 9.99, p3: 13.99 }, thesslstore: { name: "Sectigo PositiveSSL", p1: 6.49, p2: 10.99, p3: 15.49 }, gogetssl: { name: "Sectigo PositiveSSL DV", p1: 4.99, p2: 8.99, p3: 12.99 }, ssl2buy: { name: "Comodo PositiveSSL", p1: 5.49, p2: 9.49, p3: 14.49 } },
  { type: "DV Wildcard", nicsrs: { name: "Sectigo Essential Wildcard", p1: 59.99, p2: 99.99, p3: 139.99 }, thesslstore: { name: "Sectigo Essential Wildcard", p1: 62.00, p2: 105.00, p3: 148.00 }, gogetssl: { name: "Sectigo PositiveSSL Wildcard", p1: 49.99, p2: 89.99, p3: 129.99 }, ssl2buy: { name: "Comodo Essential Wildcard", p1: 55.00, p2: 95.00, p3: null } },
  { type: "OV SSL", nicsrs: { name: "Sectigo InstantSSL", p1: 19.99, p2: 34.99, p3: 49.99 }, thesslstore: { name: "DigiCert Basic OV", p1: 175.00, p2: 320.00, p3: 450.00 }, gogetssl: { name: "Sectigo InstantSSL", p1: 22.00, p2: 38.00, p3: 54.00 }, ssl2buy: { name: "Comodo InstantSSL", p1: 25.00, p2: 42.00, p3: null } },
  { type: "EV SSL", nicsrs: { name: "Sectigo EV SSL", p1: 69.99, p2: 129.99, p3: 179.99 }, thesslstore: { name: "DigiCert Secure Site EV", p1: 295.00, p2: 540.00, p3: 780.00 }, gogetssl: { name: "Sectigo EV SSL", p1: 59.99, p2: 109.99, p3: 159.99 }, ssl2buy: { name: "Comodo EV SSL", p1: 75.00, p2: null, p3: null } },
  { type: "Multi-Domain", nicsrs: { name: "Sectigo Multi-Domain", p1: 29.99, p2: 54.99, p3: 79.99 }, thesslstore: { name: "DigiCert Multi-Domain", p1: 199.00, p2: 360.00, p3: 510.00 }, gogetssl: { name: "Sectigo Multi-Domain", p1: 32.00, p2: 58.00, p3: 84.00 }, ssl2buy: { name: "Comodo Multi-Domain", p1: 35.00, p2: 60.00, p3: null } },
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

const Badge = ({ children, color, bg }) => (
  <span style={{ display: "inline-flex", alignItems: "center", padding: "2px 8px", borderRadius: 3, fontSize: 11, fontWeight: 600, color: color || COLORS.text, background: bg || COLORS.bg, border: `1px solid ${color || COLORS.border}22`, whiteSpace: "nowrap" }}>{children}</span>
);

const ProviderBadge = ({ slug }) => {
  const p = PROVIDERS[slug];
  if (!p) return null;
  return <Badge color={p.color} bg={`${p.color}12`}>{p.name}{p.tier === 2 && <span style={{ marginLeft: 4, fontSize: 9, opacity: 0.7 }}>T2</span>}</Badge>;
};

const StatusBadge = ({ status }) => {
  const map = { "Issued": { c: COLORS.success, b: "#f6ffed" }, "Pending": { c: COLORS.warning, b: "#fffbe6" }, "Processing": { c: COLORS.primary, b: "#e6f7ff" }, "Awaiting Config": { c: "#fa8c16", b: "#fff7e6" }, "Expired": { c: COLORS.danger, b: "#fff1f0" }, "connected": { c: COLORS.success, b: "#f6ffed" } };
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
  const data = [{ name: "NicSRS", pct: 38, color: PROVIDERS.nicsrs.color }, { name: "TheSSLStore", pct: 28, color: PROVIDERS.thesslstore.color }, { name: "GoGetSSL", pct: 22, color: PROVIDERS.gogetssl.color }, { name: "SSL2Buy", pct: 12, color: PROVIDERS.ssl2buy.color }];
  let offset = 0;
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>
      <svg width="120" height="120" viewBox="0 0 120 120">
        {data.map((d, i) => {
          const r = 50, circ = 2 * Math.PI * r, dash = (d.pct / 100) * circ, gap = circ - dash;
          const el = <circle key={i} cx="60" cy="60" r={r} fill="none" stroke={d.color} strokeWidth="16" strokeDasharray={`${dash} ${gap}`} strokeDashoffset={-offset} transform="rotate(-90 60 60)" />;
          offset += dash;
          return el;
        })}
        <text x="60" y="56" textAnchor="middle" fontSize="18" fontWeight="700" fill={COLORS.heading}>847</text>
        <text x="60" y="72" textAnchor="middle" fontSize="10" fill={COLORS.secondary}>Total</text>
      </svg>
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {data.map(d => (
          <div key={d.name} style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12 }}>
            <div style={{ width: 10, height: 10, borderRadius: 2, background: d.color }} />
            <span style={{ color: COLORS.text }}>{d.name}</span>
            <span style={{ color: COLORS.secondary, marginLeft: "auto", fontWeight: 600 }}>{d.pct}%</span>
          </div>
        ))}
      </div>
    </div>
  );
};

const MonthlyBars = () => {
  const months = ["Sep", "Oct", "Nov", "Dec", "Jan", "Feb"];
  const data = [[28, 22, 18, 8], [35, 25, 20, 10], [42, 30, 24, 12], [38, 28, 22, 11], [45, 32, 26, 14], [48, 35, 28, 15]];
  const max = Math.max(...data.map(d => d.reduce((a, b) => a + b, 0)));
  const provColors = [PROVIDERS.nicsrs.color, PROVIDERS.thesslstore.color, PROVIDERS.gogetssl.color, PROVIDERS.ssl2buy.color];
  return (
    <div style={{ display: "flex", alignItems: "flex-end", gap: 8, height: 120, paddingTop: 10 }}>
      {months.map((m, mi) => {
        const total = data[mi].reduce((a, b) => a + b, 0);
        const h = (total / max) * 100;
        let y = 0;
        return (
          <div key={m} style={{ flex: 1, display: "flex", flexDirection: "column", alignItems: "center" }}>
            <div style={{ width: "100%", height: 100, display: "flex", flexDirection: "column-reverse", borderRadius: "3px 3px 0 0", overflow: "hidden" }}>
              {data[mi].map((v, vi) => {
                const segH = (v / max) * 100;
                return <div key={vi} style={{ width: "100%", height: `${segH}%`, background: provColors[vi], minHeight: v > 0 ? 2 : 0 }} />;
              })}
            </div>
            <div style={{ fontSize: 10, color: COLORS.secondary, marginTop: 4 }}>{m}</div>
          </div>
        );
      })}
    </div>
  );
};

// ── Pages ──

const DashboardPage = () => (
  <div>
    <div style={{ display: "flex", gap: 12, marginBottom: 20, flexWrap: "wrap" }}>
      {STATS.map(s => <StatCard key={s.label} stat={s} />)}
    </div>
    <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 20 }}>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>📊 Orders by Provider</div>
        <ProviderDonut />
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, padding: 16 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 12 }}>📈 Monthly Trend</div>
        <MonthlyBars />
      </div>
    </div>
    <div style={{ display: "grid", gridTemplateColumns: "3fr 1fr", gap: 16 }}>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 14, fontWeight: 600, color: COLORS.heading }}>🕐 Recent Orders</div>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Order ID", "Provider", "Domain", "Product", "Client", "Status", "Date"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{ORDERS.map(o => (
            <tr key={o.id} style={{ borderBottom: `1px solid #f0f0f0` }}>
              <td style={{ padding: "10px 12px" }}><a href="#" style={{ color: COLORS.primary, textDecoration: "none", fontWeight: 500 }}>{o.id}</a></td>
              <td style={{ padding: "10px 12px" }}><ProviderBadge slug={o.provider} /></td>
              <td style={{ padding: "10px 12px", fontFamily: "monospace", fontSize: 11 }}>{o.domain}</td>
              <td style={{ padding: "10px 12px" }}>{o.product}</td>
              <td style={{ padding: "10px 12px" }}>{o.client}</td>
              <td style={{ padding: "10px 12px" }}><StatusBadge status={o.status} /></td>
              <td style={{ padding: "10px 12px", color: COLORS.secondary }}>{o.date}</td>
            </tr>
          ))}</tbody>
        </table>
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 14, fontWeight: 600, color: COLORS.heading }}>💰 Provider Balance</div>
        <div style={{ padding: 12 }}>
          {Object.entries(PROVIDERS).map(([k, v]) => (
            <div key={k} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "8px 0", borderBottom: `1px solid #f0f0f0` }}>
              <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                <div style={{ width: 8, height: 8, borderRadius: "50%", background: v.color }} />
                <span style={{ fontSize: 12, color: COLORS.text }}>{v.name}</span>
              </div>
              <span style={{ fontSize: 12, fontWeight: 600, color: COLORS.heading }}>{v.balance}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  </div>
);

const ProvidersPage = () => {
  const [showForm, setShowForm] = useState(false);
  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 16 }}>
        <div style={{ fontSize: 13, color: COLORS.secondary }}>Manage SSL certificate providers. Tier 1 = Full API, Tier 2 = Limited API.</div>
        <button onClick={() => setShowForm(!showForm)} style={{ padding: "8px 16px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 13, fontWeight: 500, cursor: "pointer" }}>+ Add Provider</button>
      </div>
      {showForm && (
        <div style={{ background: "#e6f7ff", border: `1px solid ${COLORS.primary}44`, borderRadius: 6, padding: 20, marginBottom: 16 }}>
          <div style={{ fontSize: 14, fontWeight: 600, color: COLORS.heading, marginBottom: 16 }}>Add New Provider</div>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            <div><label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Provider Type</label>
              <select style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13 }}>
                <option>Select provider...</option><option>NicSRS (Tier 1)</option><option>TheSSLStore (Tier 1)</option><option>GoGetSSL (Tier 1)</option><option>SSL2Buy (Tier 2)</option>
              </select></div>
            <div><label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>Display Name</label>
              <input type="text" placeholder="e.g. My NicSRS Account" style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} /></div>
            <div><label style={{ fontSize: 12, fontWeight: 500, color: COLORS.text, display: "block", marginBottom: 4 }}>API Token / Key</label>
              <input type="password" placeholder="Enter API credentials" style={{ width: "100%", padding: "8px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, boxSizing: "border-box" }} /></div>
            <div style={{ display: "flex", alignItems: "flex-end", gap: 8 }}>
              <label style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12, color: COLORS.text }}>
                <input type="checkbox" /> Sandbox Mode
              </label>
              <button style={{ padding: "8px 16px", background: COLORS.success, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔌 Test Connection</button>
            </div>
          </div>
          <div style={{ display: "flex", gap: 8, marginTop: 16 }}>
            <button style={{ padding: "8px 20px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 13, cursor: "pointer" }}>Save Provider</button>
            <button onClick={() => setShowForm(false)} style={{ padding: "8px 20px", background: COLORS.white, color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 13, cursor: "pointer" }}>Cancel</button>
          </div>
        </div>
      )}
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Provider", "Tier", "Status", "Sandbox", "Products", "Last Sync", "Balance", "Actions"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{PROVIDER_LIST.map(p => (
            <tr key={p.slug} style={{ borderBottom: `1px solid #f0f0f0` }}>
              <td style={{ padding: "10px 12px" }}><div style={{ display: "flex", alignItems: "center", gap: 8 }}><div style={{ width: 8, height: 8, borderRadius: "50%", background: PROVIDERS[p.slug].color }} /><span style={{ fontWeight: 600 }}>{p.name}</span></div></td>
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
};

const ProductsPage = () => {
  const [filter, setFilter] = useState("all");
  const filtered = filter === "all" ? ALL_PRODUCTS : ALL_PRODUCTS.filter(p => p.provider === filter);
  return (
    <div>
      <div style={{ display: "flex", gap: 8, marginBottom: 16, flexWrap: "wrap", alignItems: "center" }}>
        <select value={filter} onChange={e => setFilter(e.target.value)} style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option value="all">All Providers</option>
          {Object.entries(PROVIDERS).map(([k, v]) => <option key={k} value={k}>{v.name}</option>)}
        </select>
        <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option>All Types</option><option>DV</option><option>OV</option><option>EV</option>
        </select>
        <input type="text" placeholder="Search products..." style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, width: 200 }} />
        <div style={{ marginLeft: "auto" }}>
          <button style={{ padding: "7px 16px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔄 Sync All Products</button>
        </div>
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12 }}>
          <thead><tr style={{ background: "#fafafa" }}>
            {["Provider", "Code", "Product Name", "CA", "Type", "Wildcard", "SAN", "Price", "Linked", "Actions"].map(h => (
              <th key={h} style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}` }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>{filtered.map((p, i) => (
            <tr key={i} style={{ borderBottom: `1px solid #f0f0f0` }}>
              <td style={{ padding: "10px 12px" }}><ProviderBadge slug={p.provider} /></td>
              <td style={{ padding: "10px 12px" }}><code style={{ background: "#f5f5f5", padding: "2px 6px", borderRadius: 3, fontSize: 11 }}>{p.code}</code></td>
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
  const provKeys = ["nicsrs", "thesslstore", "gogetssl", "ssl2buy"];
  const findMin = (row) => {
    let min = Infinity, key = "";
    provKeys.forEach(k => { if (row[k]?.p1 && row[k].p1 < min) { min = row[k].p1; key = k; } });
    return key;
  };
  return (
    <div>
      <div style={{ display: "flex", gap: 8, marginBottom: 16, alignItems: "center" }}>
        <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option>All Certificate Types</option><option>DV SSL</option><option>OV SSL</option><option>EV SSL</option><option>Wildcard</option><option>Multi-Domain</option>
        </select>
        <select style={{ padding: "7px 12px", border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12 }}>
          <option>All CAs</option><option>Sectigo</option><option>DigiCert</option><option>GlobalSign</option><option>Comodo</option>
        </select>
        <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
          <button style={{ padding: "7px 16px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, cursor: "pointer" }}>🔄 Refresh Prices</button>
          <button style={{ padding: "7px 16px", background: COLORS.white, color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>📥 Export CSV</button>
        </div>
      </div>
      <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6, overflow: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 12, minWidth: 800 }}>
          <thead>
            <tr style={{ background: "#fafafa" }}>
              <th style={{ padding: "10px 12px", textAlign: "left", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, width: 120 }}>Type</th>
              {provKeys.map(k => (
                <th key={k} style={{ padding: "10px 12px", textAlign: "center", fontWeight: 600, color: COLORS.heading, borderBottom: `1px solid ${COLORS.border}`, borderLeft: `1px solid #f0f0f0` }}>
                  <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 2 }}>
                    <ProviderBadge slug={k} />
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>{PRODUCTS_COMPARE.map((row, ri) => {
            const minKey = findMin(row);
            return (
              <tr key={ri} style={{ borderBottom: `1px solid #f0f0f0` }}>
                <td style={{ padding: "12px", fontWeight: 600, color: COLORS.heading, background: "#fafafa" }}>{row.type}</td>
                {provKeys.map(k => {
                  const d = row[k];
                  const isMin = k === minKey;
                  return (
                    <td key={k} style={{ padding: "10px 12px", textAlign: "center", borderLeft: `1px solid #f0f0f0`, background: isMin ? "#f6ffed" : "transparent" }}>
                      <div style={{ fontSize: 10, color: COLORS.secondary, marginBottom: 4 }}>{d?.name || "—"}</div>
                      <div style={{ fontWeight: 700, fontSize: 16, color: isMin ? COLORS.success : COLORS.heading }}>
                        {d?.p1 ? `$${d.p1.toFixed(2)}` : "—"}
                        {isMin && <span style={{ fontSize: 9, marginLeft: 4, background: COLORS.success, color: COLORS.white, padding: "1px 4px", borderRadius: 2 }}>BEST</span>}
                      </div>
                      <div style={{ fontSize: 10, color: COLORS.secondary, marginTop: 2 }}>
                        {d?.p2 ? `2yr: $${d.p2.toFixed(2)}` : ""}{d?.p3 ? ` · 3yr: $${d.p3.toFixed(2)}` : ""}
                      </div>
                    </td>
                  );
                })}
              </tr>
            );
          })}</tbody>
        </table>
      </div>
      <div style={{ marginTop: 12, fontSize: 11, color: COLORS.secondary, display: "flex", gap: 16 }}>
        <span>💡 Green = Lowest price for this certificate type</span>
        <span>📅 Prices cached 2 hours ago</span>
        <span>💲 All prices in USD (reseller cost)</span>
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
          <tr key={o.id} style={{ borderBottom: `1px solid #f0f0f0` }}>
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

const OrderDetailPage = () => {
  const o = ORDERS[0];
  return (
    <div>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
        <button onClick={() => {}} style={{ padding: "4px 10px", background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 4, fontSize: 12, cursor: "pointer" }}>← Back</button>
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
              {[["🔄 Refresh Status", COLORS.primary, "#e6f7ff"], ["📥 Download Certificate", COLORS.success, "#f6ffed"], ["🔁 Reissue Certificate", "#722ed1", "#f9f0ff"], ["📧 Resend DCV Email", COLORS.warning, "#fffbe6"], ["🚫 Revoke Certificate", COLORS.danger, "#fff1f0"]].map(([label, color, bg]) => (
                <button key={label} style={{ padding: "10px 16px", background: bg, color: color, border: `1px solid ${color}22`, borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer", textAlign: "left" }}>{label}</button>
              ))}
            </div>
          </div>
          <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 6 }}>
            <div style={{ padding: "12px 16px", borderBottom: `1px solid ${COLORS.border}`, fontSize: 13, fontWeight: 600, color: COLORS.heading }}>👤 Client Info</div>
            <div style={{ padding: 12, fontSize: 12 }}>
              <div style={{ marginBottom: 8 }}><span style={{ color: COLORS.secondary }}>Client:</span> <span style={{ fontWeight: 500 }}>HVN Group</span></div>
              <div style={{ marginBottom: 8 }}><span style={{ color: COLORS.secondary }}>Email:</span> admin@hvn.vn</div>
              <div style={{ marginBottom: 8 }}><span style={{ color: COLORS.secondary }}>Service ID:</span> #4521</div>
              <div><span style={{ color: COLORS.secondary }}>WHMCS Product:</span> Sectigo PositiveSSL DV</div>
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
      <span style={{ fontSize: 16, fontWeight: 600, color: COLORS.heading }}>Order AIO-1844</span>
      <StatusBadge status="Awaiting Config" />
      <ProviderBadge slug="ssl2buy" />
      <Badge color={COLORS.warning} bg="#fffbe6">⚠️ Limited API — Tier 2 Provider</Badge>
    </div>
    <div style={{ background: "#fff7e6", border: `1px solid #ffd591`, borderRadius: 6, padding: 16, marginBottom: 16, display: "flex", gap: 12, alignItems: "flex-start" }}>
      <span style={{ fontSize: 20 }}>🔗</span>
      <div>
        <div style={{ fontWeight: 600, color: "#d48806", marginBottom: 4 }}>SSL2Buy Configuration Portal</div>
        <div style={{ fontSize: 12, color: "#d48806", lineHeight: 1.6, marginBottom: 12 }}>
          This certificate is managed through SSL2Buy's white-label configuration portal. Use the link and PIN below to configure, validate, reissue, or download your certificate.
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
          <button style={{ padding: "10px 16px", background: "#fffbe6", color: "#d48806", border: `1px solid #d4880622`, borderRadius: 4, fontSize: 12, fontWeight: 500, cursor: "pointer", textAlign: "left" }}>📧 Resend Approval Email</button>
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
        {[["general", "General"], ["sync", "Auto-Sync"], ["notifications", "Notifications"], ["security", "Security"]].map(([k, l]) => (
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
              {[["Last Sync", "2026-02-11 10:45:00"], ["Next Sync", "2026-02-11 11:15:00"], ["Pending Orders", "23"], ["Error Count", "0"]].map(([k, v]) => (
                <div key={k} style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 8 }}>
                  <span style={{ color: COLORS.secondary }}>{k}:</span>
                  <span style={{ fontWeight: 500, color: COLORS.heading }}>{v}</span>
                </div>
              ))}
              <button style={{ marginTop: 8, padding: "8px 16px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 4, fontSize: 12, cursor: "pointer", width: "100%" }}>Run Sync Now</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const ClientConfigPage = () => (
  <div style={{ maxWidth: 700, margin: "0 auto" }}>
    <div style={{ background: COLORS.white, border: `1px solid ${COLORS.border}`, borderRadius: 8 }}>
      <div style={{ padding: "20px 24px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <div><div style={{ fontSize: 18, fontWeight: 600, color: COLORS.heading }}>🔒 SSL Certificate</div><div style={{ fontSize: 12, color: COLORS.secondary, marginTop: 2 }}>Configure your certificate — Sectigo PositiveSSL</div></div>
        <ProviderBadge slug="nicsrs" />
      </div>
      <div style={{ padding: "16px 24px", borderBottom: `1px solid ${COLORS.border}`, display: "flex", gap: 0 }}>
        {[["1", "CSR", true], ["2", "Validation", false], ["3", "Contacts", false], ["4", "Review", false]].map(([n, l, active], i) => (
          <div key={n} style={{ flex: 1, display: "flex", alignItems: "center", gap: 8, padding: "8px 12px" }}>
            <div style={{ width: 24, height: 24, borderRadius: "50%", background: active ? COLORS.primary : COLORS.bg, color: active ? COLORS.white : COLORS.secondary, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 12, fontWeight: 600, flexShrink: 0 }}>{n}</div>
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
        <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
          <button style={{ padding: "10px 20px", background: COLORS.white, color: COLORS.text, border: `1px solid ${COLORS.border}`, borderRadius: 6, fontSize: 13, cursor: "pointer" }}>💾 Save Draft</button>
          <button style={{ padding: "10px 24px", background: COLORS.primary, color: COLORS.white, border: "none", borderRadius: 6, fontSize: 13, fontWeight: 600, cursor: "pointer" }}>Next Step →</button>
        </div>
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
  order_detail: { label: "Order Detail", icon: "🔒", component: OrderDetailPage },
  tier2_detail: { label: "Tier 2 Order", icon: "🔗", component: Tier2OrderDetail },
  settings: { label: "Settings", icon: "⚙️", component: SettingsPage },
  client_config: { label: "Client: Configure", icon: "🖥️", component: ClientConfigPage },
};

export default function AIOSSLMockup() {
  const [page, setPage] = useState("dashboard");
  const Pg = PAGES[page]?.component || DashboardPage;
  const mainTabs = ["dashboard", "providers", "products", "compare", "orders", "settings"];
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