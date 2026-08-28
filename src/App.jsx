import Navbar from "./components/Navbar.jsx";
import Hero from "./components/Hero.jsx";
import Overview from "./components/Overview.jsx";
import Systems from "./components/Systems.jsx";
import Framework from "./components/Framework.jsx";
import Proponents from "./components/Proponents.jsx";
import Footer from "./components/Footer.jsx";
import { useReveal } from "./hooks.js";

export default function App() {
  useReveal();
  return (
    <div className="relative min-h-screen overflow-x-hidden">
      <Navbar />
      <main>
        <Hero />
        <Overview />
        <Systems />
        <Framework />
        <Proponents />
      </main>
      <Footer />
    </div>
  );
}
