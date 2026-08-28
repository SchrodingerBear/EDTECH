import { GraduationCap, Mail, MapPin } from "lucide-react";
import { meta, systems } from "../data.js";

export default function Footer() {
  return (
    <footer className="relative border-t border-white/10 bg-ink-900/60">
      <div className="container-x py-16">
        <div className="grid gap-10 md:grid-cols-3">
          <div>
            <div className="flex items-center gap-3">
              <span className="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-ink-800">
                <GraduationCap className="h-5 w-5 text-brand-300" />
              </span>
              <div>
                <p className="text-sm font-semibold text-white">{meta.institution}</p>
                <p className="text-xs text-slate-500">{meta.college}</p>
              </div>
            </div>
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">
              A formal capstone research manuscript complying with institutional thesis formatting
              standards and academic nomenclature.
            </p>
          </div>

          <div>
            <h4 className="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Systems</h4>
            <ul className="space-y-2.5">
              {systems.map((s) => (
                <li key={s.id}>
                  <a href={`#${s.id}`} className="text-sm text-slate-400 transition-colors hover:text-white">
                    {s.shortTitle}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h4 className="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Affiliation</h4>
            <ul className="space-y-2.5 text-sm text-slate-400">
              <li>{meta.center}</li>
              <li>{meta.academicYear}</li>
            </ul>
          </div>
        </div>

        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 sm:flex-row">
          <p className="text-xs text-slate-500">
            © {new Date().getFullYear()} {meta.institution}. All rights reserved.
          </p>
          <p className="text-xs text-slate-500">
            Capstone Research Portfolio · {meta.college}
          </p>
        </div>
      </div>
    </footer>
  );
}
