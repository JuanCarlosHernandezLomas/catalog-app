import { useState } from 'react'
import './App.css'
import { useEffect} from "react";

export default function App() {
  const [products, setProducts] = useState([]);
  const API = "http://localhost:8000";

  useEffect(() => {
    fetch(`${API}/api/products`)
      .then((r) => r.json())
      .then(setProducts)
      .catch(console.error);
  }, []);

  const downloadPdf = async () => {
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
      <h2>Catálogo (React + PHP)</h2>

      <button onClick={downloadPdf} style={{ padding: 10, fontWeight: "bold" }}>
        Descargar PDF (desde PHP)
      </button>

      <p>Total productos: {products.length}</p>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 10 }}>
        {products.slice(0, 8).map((p) => (
          <div key={p.id} style={{ border: "1px solid #ddd", padding: 10 }}>
            <img src={p.imageUrl} alt="" style={{ width: "100%", height: 120, objectFit: "cover" }} />
            <div style={{ fontWeight: 900 }}>{p.name}</div>
            <div>${p.price} MXN</div>
            <div style={{ opacity: 0.7 }}>Código: {p.id}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
