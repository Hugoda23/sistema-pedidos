// Marcador para módulos que aún no se construyen.
export default function Pendiente({ titulo }) {
  return <h1 className="text-2xl font-bold text-slate-800">{titulo} <span className="text-base font-normal text-slate-400">(próximamente)</span></h1>
}
