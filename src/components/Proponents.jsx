import { Users, Award, Mail } from "lucide-react";
import { proponents, panel } from "../data.js";

function PersonCard({ person, accent }) {
  const accentClasses = {
    brand: { ring: "ring-brand-400/30", text: "text-brand-300", bg: "bg-brand-500/10" },
    emerald: { ring: "ring-emerald-400/30", text: "text-emerald-300", bg: "bg-emerald-500/10" },
    amber: { ring: "ring-amber-400/30", text: "text-amber-300", bg: "bg-amber-500/10" },
    rose: { ring: "ring-rose-400/30", text: "text-rose-300", bg: "bg-rose-500/10" },
    accent: { ring: "ring-accent-400/30", text: "text-accent-400", bg: "bg-accent-500/10" },
  };
  const a = accentClasses[accent];
  return (
    <div className="card-surface hover-lift p-6 text-center">
      <div className={`mx-auto grid h-16 w-16 place-items-center rounded-full ${a.bg} ring-2 ${a.ring}`}>
        <span className={`text-lg font-bold ${a.text}`}>{person.initials}</span>
      </div>
      <h4 className="mt-4 text-base font-semibold text-white">{person.name}</h4>
      <p className={`mt-1 text-xs font-semibold uppercase tracking-wider ${a.text}`}>{person.role}</p>
    </div>
  );
}

export default function Proponents() {
  return (
    <section id="proponents" className="section-pad relative overflow-hidden">
      <div className="absolute top-1/4 left-1/4 h-[350px] w-[350px] rounded-full bg-brand-700/10 blur-[120px]" />
      <div className="container-x relative">
        <div className="reveal mx-auto mb-16 max-w-2xl text-center">
          <span className="eyebrow">Research Proponents</span>
          <h2 className="mt-4 text-headline font-bold text-white">Investigative Team & Panel</h2>
          <p className="mt-5 text-base leading-relaxed text-slate-400">
            The capstone research is conducted by a three-member investigative team under the
            guidance of a designated research adviser and program head.
          </p>
        </div>

        {/* Proponents */}
        <div className="reveal mb-12">
          <div className="mb-6 flex items-center gap-3">
            <Users className="h-5 w-5 text-brand-300" />
            <h3 className="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Research Proponents</h3>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            {proponents.map((p, i) => (
              <div key={p.name} className="reveal" style={{ transitionDelay: `${i * 80}ms` }}>
                <PersonCard person={p} accent="brand" />
              </div>
            ))}
          </div>
        </div>

        {/* Panel */}
        <div className="reveal">
          <div className="mb-6 flex items-center gap-3">
            <Award className="h-5 w-5 text-amber-400" />
            <h3 className="text-sm font-bold uppercase tracking-[0.2em] text-slate-400">Adviser & Program Head</h3>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            {panel.map((p, i) => (
              <div key={p.name} className="reveal" style={{ transitionDelay: `${i * 80}ms` }}>
                <PersonCard person={p} accent={i === 0 ? "amber" : "emerald"} />
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
