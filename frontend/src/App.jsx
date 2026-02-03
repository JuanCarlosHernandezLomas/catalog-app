
import './App.css'

import { useEffect, useMemo, useState } from "react";

const API = "http://localhost:8000";

export default function App() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // filtros básicos
  const [category, setCategory] = useState("ALL");
  const [q, setQ] = useState("");

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        setError("");
        const res = await fetch(`${API}/api/products`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        setProducts(data);
      } catch (e) {
        setError(String(e.message || e));
      } finally {
        setLoading(false);
      }
    };
    load();
  }, []);

  const categories = useMemo(() => {
    const set = new Set(products.map((p) => p.category).filter(Boolean));
    return ["ALL", ...Array.from(set).sort()];
  }, [products]);

  const filtered = useMemo(() => {
    return products.filter((p) => {
      const matchCat = category === "ALL" ? true : p.category === category;
      const text = `${p.name} ${p.code} ${p.presentation || ""}`.toLowerCase();
      const matchQ = q.trim() ? text.includes(q.toLowerCase()) : true;
      return matchCat && matchQ;
    });
  }, [products, category, q]);

  const downloadPdf = async () => {
    // descarga el PDF generado por PHP
    const res = await fetch(`${API}/api/catalog/pdf`);
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = "catalogo.pdf";
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div style={{ padding: 20, fontFamily: "Arial" }}>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 12, alignItems: "center" }}>
        <div>
          <h2 style={{ margin: 0 }}>Catálogo</h2>
          <div style={{ opacity: 0.8 }}>Productos: {filtered.length}</div>
        </div>

        <button onClick={downloadPdf} style={{ padding: "10px 14px", fontWeight: 800 }}>
          Descargar PDF
        </button>
      </div>

      <div style={{ marginTop: 14, display: "flex", gap: 10, flexWrap: "wrap" }}>
        <select value={category} onChange={(e) => setCategory(e.target.value)} style={{ padding: 8 }}>
          {categories.map((c) => (
            <option key={c} value={c}>{c}</option>
          ))}
        </select>

        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Buscar por nombre, código o presentación..."
          style={{ padding: 8, minWidth: 280 }}
        />
      </div>

      {loading && <p>Cargando...</p>}
      {error && <p style={{ color: "crimson" }}>Error: {error}</p>}

      <div style={{ marginTop: 16, display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12 }}>
        {filtered.map((p) => (
          <div key={p.id} style={{ border: "1px solid #ddd", borderRadius: 12, overflow: "hidden", background: "#fff" }}>
            <div style={{ height: 140, background: "#eee" }}>
              <img
                src={p.imageUrl}
                alt={p.name}
                style={{ width: "100%", height: "100%", objectFit: "cover" }}
                onError={(e) => (e.currentTarget.src = "https://via.placeholder.com/600x450.png?text=Sin+imagen")}
              />
            </div>

            <div style={{ padding: 10 }}>
              <div style={{ fontWeight: 900 }}>{p.name}</div>
              <div style={{ opacity: 0.75, fontSize: 12 }}>Código: {p.code}</div>

              <div style={{ marginTop: 8, display: "flex", justifyContent: "space-between", gap: 10 }}>
                <div style={{ fontWeight: 900 }}>${p.price} MXN</div>
                <div style={{ fontSize: 12, opacity: 0.8 }}>
                  {p.unit}{p.presentation ? ` • ${p.presentation}` : ""}
                </div>
              </div>

              {p.type === "bolo" && p.includes?.length > 0 && (
                <div style={{ marginTop: 10, background: "#f7f7f7", borderRadius: 10, padding: 8 }}>
                  <div style={{ fontWeight: 900, fontSize: 12 }}>Incluye:</div>
                  <ul style={{ margin: "6px 0 0", paddingLeft: 18, fontSize: 12 }}>
                    {p.includes.map((it, idx) => (
                      <li key={idx}>{it.name}</li>
                    ))}
                  </ul>
                </div>
              )}

              <div style={{ marginTop: 10, fontSize: 12, opacity: 0.75 }}>
                Categoría: {p.category}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
