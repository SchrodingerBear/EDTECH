import { systems } from "../data.js";
import SystemCard from "./SystemCard.jsx";

export default function Systems() {
  return (
    <section id="systems" className="section-pad relative overflow-hidden">
      <div className="absolute top-1/3 left-0 h-[400px] w-[400px] rounded-full bg-brand-700/10 blur-[120px]" />
      <div className="container-x relative">
        <div className="reveal mx-auto mb-16 max-w-2xl text-center">
          <span className="eyebrow">The Three Systems</span>
          <h2 className="mt-4 text-headline font-bold text-white">
            Technical Systems Specifications
          </h2>
          <p className="mt-5 text-base leading-relaxed text-slate-400">
            Each system is presented with its official research title, client locale, core
            architectural framework, and functional domains — structured for direct integration
            into the manuscript's Chapter 1 and Chapter 2 sections.
          </p>
        </div>

        <div className="space-y-6">
          {systems.map((sys, i) => (
            <SystemCard key={sys.id} sys={sys} flip={i % 2 === 1} />
          ))}
        </div>
      </div>
    </section>
  );
}
