import { GraduationCap, Brain, BookOpen, ScrollText, Target, Microscope } from "lucide-react";
import { meta } from "../data.js";

const pillars = [
  {
    icon: Target,
    title: "Objective",
    desc: "Develop and evaluate three AI-integrated systems addressing distinct institutional domains: educational navigation, property management, and emergency response.",
  },
  {
    icon: Microscope,
    title: "Methodology",
    desc: "Quantitative evaluation using the ISO/IEC 25010 Software Quality Standard to measure Usability, Efficiency, and Security across each system.",
  },
  {
    icon: Brain,
    title: "Theoretical Lens",
    desc: "Technology Acceptance Model (TAM, Davis 1989) assessing Perceived Usefulness and Perceived Ease of Use among administrators and end-users.",
  },
];

export default function Overview() {
  return (
    <section id="overview" className="section-pad relative">
      <div className="container-x">
        <div className="reveal mx-auto mb-16 max-w-2xl text-center">
          <span className="eyebrow">General Academic Profile</span>
          <h2 className="mt-4 text-headline font-bold text-white">
            Capstone Research at {meta.institution}
          </h2>
          <p className="mt-5 text-base leading-relaxed text-slate-400">
            This portfolio documents the formal capstone research conducted under the{" "}
            <span className="text-slate-200">{meta.college}</span> and its{" "}
            <span className="text-slate-200">{meta.center}</span>, complying with the
            institution's thesis manuscript formatting standards.
          </p>
        </div>

        {/* Institutional cards */}
        <div className="reveal grid gap-4 md:grid-cols-3">
          <div className="card-surface hover-lift p-6">
            <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-brand-500/15">
              <GraduationCap className="h-5 w-5 text-brand-300" />
            </div>
            <h3 className="text-base font-semibold text-white">Institution</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-slate-400">{meta.institution}</p>
          </div>
          <div className="card-surface hover-lift p-6">
            <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-accent-500/15">
              <BookOpen className="h-5 w-5 text-accent-400" />
            </div>
            <h3 className="text-base font-semibold text-white">College</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-slate-400">{meta.college}</p>
          </div>
          <div className="card-surface hover-lift p-6">
            <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-emerald-500/15">
              <Brain className="h-5 w-5 text-emerald-400" />
            </div>
            <h3 className="text-base font-semibold text-white">Research Center</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-slate-400">{meta.center}</p>
          </div>
        </div>

        {/* Pillars */}
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {pillars.map((p, i) => (
            <div
              key={p.title}
              className="reveal card-surface hover-lift p-6"
              style={{ transitionDelay: `${i * 80}ms` }}
            >
              <div className="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-white/5">
                <p.icon className="h-5 w-5 text-slate-300" />
              </div>
              <h3 className="text-base font-semibold text-white">{p.title}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-slate-400">{p.desc}</p>
            </div>
          ))}
        </div>

        {/* Format compliance note */}
        <div className="reveal mt-6 flex items-start gap-4 rounded-2xl border border-white/10 bg-ink-800/40 p-6">
          <ScrollText className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" />
          <p className="text-sm leading-relaxed text-slate-400">
            <span className="font-semibold text-slate-200">Manuscript Compliance:</span> All
            sections adhere to the institutional thesis format — Arial, size 11, regular, justified,
            1.5 line spacing, with margins of 1.75″ (top/left) and 1.25″ (bottom/right).
          </p>
        </div>
      </div>
    </section>
  );
}
