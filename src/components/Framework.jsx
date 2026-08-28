import { BookOpen, Gauge, Cpu, Cloud, Database, ScanEye, Layers } from "lucide-react";
import { framework } from "../data.js";

export default function Framework() {
  return (
    <section id="framework" className="section-pad relative overflow-hidden">
      <div className="absolute bottom-0 right-1/4 h-[400px] w-[400px] rounded-full bg-accent-600/10 blur-[120px]" />
      <div className="container-x relative">
        <div className="reveal mx-auto mb-16 max-w-2xl text-center">
          <span className="eyebrow">Research Framework</span>
          <h2 className="mt-4 text-headline font-bold text-white">Theoretical & Methodological Foundation</h2>
          <p className="mt-5 text-base leading-relaxed text-slate-400">
            The study grounds its evaluation in a recognized acceptance model and a standardized
            software quality instrument, ensuring academic rigor across all three systems.
          </p>
        </div>

        <div className="grid gap-6 lg:grid-cols-2">
          {/* TAM */}
          <div className="reveal card-surface hover-lift p-8">
            <div className="mb-5 flex items-center gap-3">
              <div className="grid h-11 w-11 place-items-center rounded-xl bg-brand-500/15">
                <BookOpen className="h-5 w-5 text-brand-300" />
              </div>
              <div>
                <h3 className="text-lg font-bold text-white">Theoretical Framework</h3>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                  {framework.theory.citation}
                </p>
              </div>
            </div>
            <p className="text-base font-semibold text-slate-100">{framework.theory.name}</p>
            <p className="mt-3 text-sm leading-relaxed text-slate-400">{framework.theory.desc}</p>

            <div className="mt-6 grid grid-cols-2 gap-3">
              <div className="rounded-xl border border-white/10 bg-ink-700/40 p-4">
                <p className="text-xs font-semibold uppercase tracking-wider text-brand-300">Perceived Usefulness</p>
                <p className="mt-1 text-xs text-slate-400">PU — degree to which the system enhances job performance.</p>
              </div>
              <div className="rounded-xl border border-white/10 bg-ink-700/40 p-4">
                <p className="text-xs font-semibold uppercase tracking-wider text-accent-400">Perceived Ease of Use</p>
                <p className="mt-1 text-xs text-slate-400">PEOU — degree to which the system is effort-free.</p>
              </div>
            </div>
          </div>

          {/* ISO/IEC 25010 */}
          <div className="reveal card-surface hover-lift p-8" style={{ transitionDelay: "100ms" }}>
            <div className="mb-5 flex items-center gap-3">
              <div className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-500/15">
                <Gauge className="h-5 w-5 text-emerald-400" />
              </div>
              <div>
                <h3 className="text-lg font-bold text-white">Research Instrument</h3>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Quality Evaluation</p>
              </div>
            </div>
            <p className="text-base font-semibold text-slate-100">{framework.instrument.name}</p>
            <p className="mt-3 text-sm leading-relaxed text-slate-400">{framework.instrument.desc}</p>

            <div className="mt-6 grid grid-cols-3 gap-3">
              {["Usability", "Efficiency", "Security"].map((q) => (
                <div key={q} className="rounded-xl border border-white/10 bg-ink-700/40 p-4 text-center">
                  <p className="text-sm font-semibold text-emerald-300">{q}</p>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Tech stack */}
        <div className="reveal mt-6">
          <div className="rounded-2xl border border-white/10 bg-ink-800/40 p-8">
            <h3 className="mb-6 text-sm font-bold uppercase tracking-[0.2em] text-slate-400">
              Technical Systems Specifications
            </h3>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
              {framework.stack.map((s) => {
                const SIcon = s.icon;
                return (
                  <div key={s.label} className="rounded-xl border border-white/10 bg-ink-700/30 p-5 transition-all hover:border-white/20">
                    <SIcon className="mb-3 h-5 w-5 text-slate-300" />
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">{s.label}</p>
                    <p className="mt-1 text-sm font-medium text-slate-200">{s.value}</p>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
