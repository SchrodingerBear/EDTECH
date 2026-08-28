import { Building, Layers } from "lucide-react";

const accentMap = {
  brand: {
    glow: "bg-brand-600/15",
    chip: "bg-brand-500/10 text-brand-200 border-brand-400/20",
    iconBg: "bg-gradient-to-br from-brand-500/20 to-transparent",
    iconBorder: "border-brand-400/30",
    iconText: "text-brand-200",
    numGlow: "text-brand-500/20",
    line: "from-brand-500/40",
    domainBg: "bg-brand-500/5",
    domainBorder: "border-brand-400/15",
    domainIcon: "text-brand-300",
    domainHover: "hover:border-brand-400/30",
    hoverBorder: "hover:border-brand-400/40",
    hoverShadow: "hover:shadow-brand-500/10",
    clientBg: "bg-accent-500/10",
    clientIcon: "text-accent-400",
    frameworkBg: "bg-brand-500/10",
    frameworkIcon: "text-brand-300",
  },
  emerald: {
    glow: "bg-emerald-600/15",
    chip: "bg-emerald-500/10 text-emerald-200 border-emerald-400/20",
    iconBg: "bg-gradient-to-br from-emerald-500/20 to-transparent",
    iconBorder: "border-emerald-400/30",
    iconText: "text-emerald-200",
    numGlow: "text-emerald-500/20",
    line: "from-emerald-500/40",
    domainBg: "bg-emerald-500/5",
    domainBorder: "border-emerald-400/15",
    domainIcon: "text-emerald-300",
    domainHover: "hover:border-emerald-400/30",
    hoverBorder: "hover:border-emerald-400/40",
    hoverShadow: "hover:shadow-emerald-500/10",
    clientBg: "bg-accent-500/10",
    clientIcon: "text-accent-400",
    frameworkBg: "bg-emerald-500/10",
    frameworkIcon: "text-emerald-300",
  },
  rose: {
    glow: "bg-rose-600/15",
    chip: "bg-rose-500/10 text-rose-200 border-rose-400/20",
    iconBg: "bg-gradient-to-br from-rose-500/20 to-transparent",
    iconBorder: "border-rose-400/30",
    iconText: "text-rose-200",
    numGlow: "text-rose-500/20",
    line: "from-rose-500/40",
    domainBg: "bg-rose-500/5",
    domainBorder: "border-rose-400/15",
    domainIcon: "text-rose-300",
    domainHover: "hover:border-rose-400/30",
    hoverBorder: "hover:border-rose-400/40",
    hoverShadow: "hover:shadow-rose-500/10",
    clientBg: "bg-accent-500/10",
    clientIcon: "text-accent-400",
    frameworkBg: "bg-rose-500/10",
    frameworkIcon: "text-rose-300",
  },
};

export default function SystemCard({ sys, flip }) {
  const a = accentMap[sys.accent];
  const Icon = sys.icon;
  return (
    <article
      id={sys.id}
      className={`reveal group relative overflow-hidden rounded-3xl border border-white/10 bg-ink-800/40 p-8 transition-all duration-500 hover:-translate-y-1 ${a.hoverBorder} hover:shadow-2xl ${a.hoverShadow} md:p-12`}
    >
      <div className={`absolute -top-20 ${flip ? "-left-20" : "-right-20"} h-72 w-72 rounded-full ${a.glow} blur-[100px]`} />

      <div className="relative grid gap-10 lg:grid-cols-5">
        <div className="lg:col-span-2">
          <div className="mb-6 flex items-center gap-4">
            <div className={`grid h-14 w-14 place-items-center rounded-2xl border ${a.iconBorder} ${a.iconBg}`}>
              <Icon className={`h-7 w-7 ${a.iconText}`} />
            </div>
            <span className={`text-5xl font-bold ${a.numGlow}`}>{sys.index}</span>
          </div>

          <span className={`inline-flex items-center gap-2 rounded-full border ${a.chip} px-3 py-1 text-xs font-semibold`}>
            {sys.shortTitle}
          </span>

          <h3 className="mt-4 text-xl font-bold leading-snug text-white md:text-2xl">
            {sys.officialTitle}
          </h3>

          <p className="mt-4 text-sm leading-relaxed text-slate-400">{sys.summary}</p>

          <div className="mt-6 space-y-3">
            <div className="flex items-start gap-3">
              <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${a.clientBg}`}>
                <Building className={`h-4 w-4 ${a.clientIcon}`} />
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Client</p>
                <p className="text-sm text-slate-200">{sys.client}</p>
                <p className="text-xs text-slate-500">{sys.clientNote}</p>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <div className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${a.frameworkBg}`}>
                <Layers className={`h-4 w-4 ${a.frameworkIcon}`} />
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Architectural Framework</p>
                <p className="text-sm leading-snug text-slate-200">{sys.framework}</p>
              </div>
            </div>
          </div>
        </div>

        <div className="lg:col-span-3">
          <div className="mb-5 flex items-center gap-3">
            <span className={`h-px flex-1 bg-gradient-to-r ${a.line} to-transparent`} />
            <h4 className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">Functional Domains</h4>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            {sys.domains.map((d, i) => {
              const DIcon = d.icon;
              return (
                <div
                  key={i}
                  className={`rounded-xl border ${a.domainBorder} ${a.domainBg} p-4 transition-all duration-300 ${a.domainHover}`}
                >
                  <div className="mb-2.5 flex items-center gap-2.5">
                    <DIcon className={`h-4 w-4 ${a.domainIcon}`} />
                    <h5 className="text-sm font-semibold text-white leading-tight">{d.title}</h5>
                  </div>
                  <p className="text-xs leading-relaxed text-slate-400">{d.desc}</p>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </article>
  );
}
