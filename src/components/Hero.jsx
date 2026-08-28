import { ArrowDown, MapPin, FileText, Sparkles } from "lucide-react";
import { meta, systems } from "../data.js";

export default function Hero() {
  return (
    <section id="top" className="relative flex min-h-screen items-center overflow-hidden pt-24">
      {/* Background layers */}
      <div className="absolute inset-0 bg-grid mask-fade-b opacity-60" />
      <div className="absolute -top-1/4 left-1/2 h-[600px] w-[600px] -translate-x-1/2 rounded-full bg-brand-600/20 blur-[120px] animate-pulse-glow" />
      <div className="absolute bottom-0 right-0 h-[400px] w-[400px] rounded-full bg-accent-600/10 blur-[100px]" />
      <div className="absolute bottom-1/4 left-0 h-[350px] w-[350px] rounded-full bg-emerald-600/10 blur-[100px]" />

      <div className="container-x relative z-10">
        <div className="max-w-3xl">
          <div className="reveal mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-ink-800/60 px-4 py-2 backdrop-blur-sm">
            <Sparkles className="h-3.5 w-3.5 text-accent-400" />
            <span className="text-xs font-medium tracking-wide text-slate-300">
              Capstone Research Portfolio · {meta.academicYear}
            </span>
          </div>

          <h1 className="reveal text-display font-bold text-white text-balance" style={{ transitionDelay: "80ms" }}>
            Three AI-Integrated Systems for
            <span className="block bg-gradient-to-r from-brand-300 via-accent-400 to-emerald-400 bg-clip-text text-transparent">
              Real-World Institutional Challenges
            </span>
          </h1>

          <p className="reveal mt-6 max-w-2xl text-lg leading-relaxed text-slate-400" style={{ transitionDelay: "160ms" }}>
            A formal capstone research manuscript submitted to the{" "}
            <span className="text-slate-200">{meta.college}</span>,{" "}
            <span className="text-slate-200">{meta.institution}</span>, presenting the architectural
            frameworks, functional domains, and technical specifications of three developed systems.
          </p>

          <div className="reveal mt-8 flex flex-wrap items-center gap-3" style={{ transitionDelay: "240ms" }}>
            <a
              href="#systems"
              className="group inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-3 text-sm font-semibold text-white transition-all hover:bg-brand-500 hover:shadow-lg hover:shadow-brand-500/30"
            >
              Explore the Systems
              <ArrowDown className="h-4 w-4 transition-transform group-hover:translate-y-0.5" />
            </a>
            <a
              href="#framework"
              className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-ink-800/60 px-6 py-3 text-sm font-semibold text-slate-200 backdrop-blur-sm transition-all hover:border-white/30 hover:text-white"
            >
              <FileText className="h-4 w-4 text-slate-400" />
              Research Framework
            </a>
          </div>

          {/* Quick system chips */}
          <div className="reveal mt-12 flex flex-wrap gap-2.5" style={{ transitionDelay: "320ms" }}>
            {systems.map((s) => (
              <span
                key={s.id}
                className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-ink-800/40 px-3.5 py-2 text-xs font-medium text-slate-300 backdrop-blur-sm"
              >
                <s.icon className="h-3.5 w-3.5 text-brand-300" />
                {s.shortTitle}
              </span>
            ))}
          </div>
        </div>

        {/* Floating locale card */}
        <div className="reveal absolute right-6 top-1/2 hidden -translate-y-1/2 lg:block" style={{ transitionDelay: "400ms" }}>
          <div className="animate-float rounded-2xl border border-white/10 bg-ink-800/60 p-5 backdrop-blur-md">
            <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
              <MapPin className="h-3.5 w-3.5 text-accent-400" />
              Client Locales
            </div>
            <ul className="space-y-2.5 text-sm">
              {systems.map((s) => (
                <li key={s.id} className="flex items-start gap-2">
                  <s.icon className="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                  <span className="text-slate-300">{s.client}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>

      <a
        href="#overview"
        className="absolute bottom-8 left-1/2 -translate-x-1/2 text-slate-500 transition-colors hover:text-white"
        aria-label="Scroll to overview"
      >
        <ArrowDown className="h-5 w-5 animate-bounce" />
      </a>
    </section>
  );
}
