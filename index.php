<?php
/**
 * Capstone Research Portfolio — College of Computer Studies
 * Immaculada Concepcion College · AY 2025–2026
 * Unified Portal for Three AI-Integrated Thesis Systems
 */

if (!function_exists('h')) {
  function h(?string $str): string
  {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
  }
}

$systems = [
  [
    'id' => 'ar-navigation',
    'index' => '01',
    'shortTitle' => 'AR Campus Navigation',
    'officialTitle' => 'AI-Assisted AR 360° Virtual Campus Navigation and Information System for Educational Institutions',
    'client' => 'Innovatech PH',
    'clientNote' => 'Software Development / Information Technology Department',
    'framework' => 'Web-Based Software-as-a-Service (SaaS) Platform using Three-Tier Architecture (Client-Side, Application Server, Database Server)',
    'locale' => 'Innovatech PH',
    'accent' => 'brand',
    'folder' => 'TITLE 1',
    'portalUrl' => 'TITLE 1/',
    'adminUrl' => 'TITLE 1/admin/index.php',
    'summary' => 'A multi-tenant SaaS platform that transforms standardized smartphone-captured panoramas into interactive 360° augmented-reality campus navigation experiences, enabling educational institutions to deploy independent branded virtual tours.',
    'domains' => [
      ['title' => 'AI-Integrated Panoramic Processing & Cubemap Stitching', 'desc' => 'Algorithmic conversion of standardized smartphone-captured flat projections into mathematical 360° cubic spherical environments.'],
      ['title' => 'Semantic Interactive Spatial Hotspots', 'desc' => 'Dynamic metadata binding on visual targets to execute database queries rendering room specifications, occupancy, and physical descriptors.'],
      ['title' => 'Markerless Augmented Reality Overlay Protocol', 'desc' => 'Real-time spatial tracking utilizing web-browser cameras to project digital navigation labels and geographic overlays over the live physical environment.'],
      ['title' => 'Topological 2D Floorplan Mapping & Point-to-Point Routing', 'desc' => 'A dynamic cartographic database calculating pathfinding vectors and projecting active "You Are Here" orientation nodes synchronized with the user\'s spatial perspective.'],
      ['title' => 'Natural Language Processing Facility Description Generator', 'desc' => 'Administrative AI subsystem using OpenAI API to synthesize spatial parameters and automate semantic summary generation for campus facilities.'],
      ['title' => 'Multi-School Configuration Interface (Multi-Tenant SaaS Engine)', 'desc' => 'Multi-tenant database architecture partitioning school data to allow separate institutions to deploy independent branded instances.']
    ]
  ]

];

$proponents = [
  ['name' => 'Sean Charles Vicente Pugosa', 'role' => 'Principal Investigator', 'initials' => 'SP', 'accent' => 'brand'],
  ['name' => 'Cedric Fuerzas Sy', 'role' => 'Co-Investigator', 'initials' => 'CS', 'accent' => 'brand'],
  ['name' => 'Christian Abuyabor', 'role' => 'Co-Investigator', 'initials' => 'CA', 'accent' => 'brand'],
];

$panel = [
  ['name' => 'Mr. Eser Fernandez', 'role' => 'Research Adviser', 'initials' => 'EF', 'accent' => 'amber'],
  ['name' => 'Mr. Jonathan E. Pariente, MIT', 'role' => 'Program Head / Chairman', 'initials' => 'JP', 'accent' => 'emerald'],
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Capstone Research Portfolio | Immaculada Concepcion College</title>
  <meta name="description"
    content="Capstone Research Portfolio — College of Computer Studies, Immaculada Concepcion College. Three AI-integrated systems: AR Campus Navigation, KD Building Property Management, and LGU Emergency Response.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <style>
    :root {
      --bg: #07090e;
      --card-bg: rgba(18, 22, 34, 0.7);
      --card-border: rgba(255, 255, 255, 0.08);
      --text: #e2e8f0;
      --muted: #94a3b8;
      --brand: #6366f1;
      --brand-glow: rgba(99, 102, 241, 0.25);
      --emerald: #10b981;
      --rose: #f43f5e;
      --amber: #f59e0b;
      --accent: #38bdf8;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* Ambient glow background */
    .ambient-bg {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      background:
        radial-gradient(circle at 50% -20%, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
        radial-gradient(circle at 100% 60%, rgba(56, 189, 248, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 0% 90%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
    }

    .grid-pattern {
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-size: 40px 40px;
      background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
      mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
      -webkit-mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
    }

    .container {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 1.5rem;
      position: relative;
      z-index: 1;
    }

    /* Navigation */
    header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 50;
      background: rgba(7, 9, 14, 0.8);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--card-border);
      transition: all 0.3s ease;
    }

    .nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 72px;
    }

    .logo-group {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #fff;
    }

    .logo-badge {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(56, 189, 248, 0.2));
      border: 1px solid rgba(255, 255, 255, 0.15);
      display: grid;
      place-items: center;
      font-size: 20px;
    }

    .logo-text h4 {
      font-size: 14px;
      font-weight: 700;
    }

    .logo-text p {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--muted);
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 2rem;
      list-style: none;
    }

    .nav-links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: color 0.2s;
    }

    .nav-links a:hover {
      color: #fff;
    }

    .btn-portal {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 18px;
      border-radius: 999px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      color: #fff;
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 600;
      box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
      transition: all 0.2s;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn-portal:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 22px rgba(99, 102, 241, 0.5);
    }

    /* Hero */
    .hero-section {
      padding: 160px 0 100px;
      text-align: center;
    }

    .badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 16px;
      border-radius: 999px;
      background: rgba(99, 102, 241, 0.12);
      border: 1px solid rgba(99, 102, 241, 0.3);
      color: #a5b4fc;
      font-size: 12.5px;
      font-weight: 600;
      margin-bottom: 24px;
    }

    .hero-title {
      font-size: clamp(2.4rem, 5.5vw, 4.2rem);
      font-weight: 900;
      line-height: 1.15;
      letter-spacing: -0.03em;
      color: #fff;
      max-width: 960px;
      margin: 0 auto 24px;
    }

    .gradient-text {
      background: linear-gradient(135deg, #a5b4fc 0%, #38bdf8 50%, #34d399 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero-desc {
      font-size: clamp(1rem, 2vw, 1.2rem);
      color: var(--muted);
      max-width: 780px;
      margin: 0 auto 36px;
      line-height: 1.7;
    }

    .hero-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 26px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--card-border);
      color: #fff;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      backdrop-filter: blur(8px);
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.25);
    }

    .btn-primary-large {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 28px;
      border-radius: 12px;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      text-decoration: none;
      font-size: 14px;
      font-weight: 700;
      box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
      transition: all 0.2s;
    }

    .btn-primary-large:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(99, 102, 241, 0.6);
    }

    /* Section headers */
    .section-header {
      text-align: center;
      max-width: 680px;
      margin: 0 auto 56px;
    }

    .section-eyebrow {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--accent);
      margin-bottom: 12px;
    }

    .section-title {
      font-size: clamp(1.8rem, 3.5vw, 2.6rem);
      font-weight: 800;
      letter-spacing: -0.025em;
      color: #fff;
      margin-bottom: 16px;
    }

    .section-desc {
      font-size: 15px;
      color: var(--muted);
      line-height: 1.7;
    }

    /* System Cards */
    .systems-container {
      display: grid;
      gap: 36px;
      margin-bottom: 120px;
    }

    .system-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 28px;
      padding: 40px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .system-card:hover {
      border-color: rgba(255, 255, 255, 0.2);
      transform: translateY(-3px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .system-card.brand-accent {
      box-shadow: inset 0 0 80px rgba(99, 102, 241, 0.04);
    }

    .system-card.emerald-accent {
      box-shadow: inset 0 0 80px rgba(16, 185, 129, 0.04);
    }

    .system-card.rose-accent {
      box-shadow: inset 0 0 80px rgba(244, 63, 94, 0.04);
    }

    .system-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 36px;
    }

    @media (min-width: 992px) {
      .system-grid {
        grid-template-columns: 2fr 3fr;
        gap: 48px;
      }
    }

    .system-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .system-num {
      font-size: 42px;
      font-weight: 900;
      opacity: 0.2;
      line-height: 1;
    }

    .system-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      border: 1px solid currentColor;
    }

    .system-chip.brand {
      color: #818cf8;
      background: rgba(99, 102, 241, 0.1);
    }

    .system-chip.emerald {
      color: #34d399;
      background: rgba(16, 185, 129, 0.1);
    }

    .system-chip.rose {
      color: #fb7185;
      background: rgba(244, 63, 94, 0.1);
    }

    .system-title {
      font-size: 22px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 14px;
      line-height: 1.35;
    }

    .system-summary {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.7;
      margin-bottom: 24px;
    }

    .system-meta-box {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 16px;
      margin-bottom: 24px;
      font-size: 13px;
    }

    .meta-row {
      display: flex;
      margin-bottom: 8px;
    }

    .meta-row:last-child {
      margin-bottom: 0;
    }

    .meta-label {
      width: 140px;
      flex-shrink: 0;
      color: var(--muted);
      font-weight: 600;
    }

    .meta-val {
      color: #e2e8f0;
    }

    .system-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn-launch {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 20px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      text-decoration: none;
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.2s;
    }

    .btn-launch.brand {
      background: #6366f1;
      box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
    }

    .btn-launch.emerald {
      background: #10b981;
      box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
    }

    .btn-launch.rose {
      background: #f43f5e;
      box-shadow: 0 4px 14px rgba(244, 63, 94, 0.35);
    }

    .btn-launch:hover {
      transform: translateY(-1px);
      filter: brightness(1.1);
    }

    .btn-admin {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      color: #cbd5e1;
      text-decoration: none;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--card-border);
      transition: all 0.2s;
    }

    .btn-admin:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    /* Functional domains grid */
    .domains-title {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
      margin-bottom: 16px;
    }

    .domains-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    @media (min-width: 640px) {
      .domains-grid {
        grid-template-columns: 1fr 1fr;
      }
    }

    .domain-card {
      background: rgba(255, 255, 255, 0.025);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 14px;
      padding: 16px;
      transition: border-color 0.2s;
    }

    .domain-card:hover {
      border-color: rgba(255, 255, 255, 0.15);
    }

    .domain-card h5 {
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .domain-card p {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.55;
    }

    /* Framework & Methodology */
    .grid-2 {
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px;
      margin-bottom: 120px;
    }

    @media (min-width: 768px) {
      .grid-2 {
        grid-template-columns: 1fr 1fr;
      }
    }

    .info-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 32px;
      backdrop-filter: blur(16px);
    }

    .info-card h3 {
      font-size: 20px;
      font-weight: 800;
      color: #fff;
      margin-bottom: 12px;
    }

    .info-card p {
      font-size: 14px;
      color: var(--muted);
      line-height: 1.7;
    }

    /* Proponents */
    .proponents-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }

    .person-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 24px;
      text-align: center;
      backdrop-filter: blur(12px);
    }

    .person-avatar {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      margin: 0 auto 16px;
      display: grid;
      place-items: center;
      font-size: 20px;
      font-weight: 800;
      background: rgba(99, 102, 241, 0.12);
      color: #818cf8;
      border: 2px solid rgba(99, 102, 241, 0.3);
    }

    .person-card h4 {
      font-size: 15px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 4px;
    }

    .person-card p {
      font-size: 12px;
      color: var(--muted);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.06em;
    }

    /* Footer */
    footer {
      border-top: 1px solid var(--card-border);
      padding: 48px 0;
      text-align: center;
      font-size: 13px;
      color: var(--muted);
      background: rgba(7, 9, 14, 0.95);
    }

    footer p {
      margin-bottom: 6px;
    }
  </style>
</head>

<body>

  <div class="ambient-bg"></div>
  <div class="grid-pattern"></div>

  <!-- Header -->
  <header>
    <div class="container nav-inner">
      <a href="#top" class="logo-group">
        <div class="logo-badge">🏛️</div>
        <div class="logo-text">
          <h4>Immaculada Concepcion College</h4>
          <p>College of Computer Studies</p>
        </div>
      </a>

      <ul class="nav-links">
        <li><a href="#overview">Overview</a></li>
        <li><a href="#systems">The Three Systems</a></li>
        <li><a href="#framework">Framework</a></li>
        <li><a href="#proponents">Proponents</a></li>
      </ul>

      <a href="#systems" class="btn-portal">
        View Thesis Systems ↓
      </a>
    </div>
  </header>

  <!-- Hero -->
  <main id="top" class="container">
    <section class="hero-section">
      <div class="badge-pill">
        ✨ Capstone Research Portfolio · AY 2025–2026
      </div>

      <h1 class="hero-title">
        Three AI-Integrated Systems for
        <span class="gradient-text">Real-World Institutional Challenges</span>
      </h1>

      <p class="hero-desc">
        A formal capstone research manuscript submitted to the <strong style="color:#fff">College of Computer
          Studies</strong>,
        <strong style="color:#fff">Immaculada Concepcion College</strong>, presenting the architectural frameworks,
        functional domains, and technical specifications of three developed systems.
      </p>

      <div class="hero-actions">
        <a href="#systems" class="btn-primary-large">
          Explore the Systems ↓
        </a>
        <a href="#framework" class="btn-secondary">
          📄 Research Framework
        </a>
      </div>
    </section>

    <!-- Overview Section -->
    <section id="overview" style="margin-bottom: 120px;">
      <div class="section-header">
        <div class="section-eyebrow">General Academic Profile</div>
        <h2 class="section-title">Capstone Research Overview</h2>
        <p class="section-desc">
          Documenting research conducted under the Center for Research, Innovation, and Development (CRID), complying
          with institutional thesis manuscript standards.
        </p>
      </div>

      <div class="grid-2">
        <div class="info-card">
          <div style="font-size: 28px; margin-bottom: 14px;">🎯</div>
          <h3>Research Objective</h3>
          <p>
            Develop and evaluate three AI-integrated systems addressing distinct institutional domains: educational
            navigation, residential property management, and municipal emergency response.
          </p>
        </div>
        <div class="info-card">
          <div style="font-size: 28px; margin-bottom: 14px;">🔬</div>
          <h3>Methodological Standard</h3>
          <p>
            Quantitative evaluation using the <strong>ISO/IEC 25010 Software Quality Standard</strong> to measure
            Usability, Efficiency, and Security, evaluated through the <strong>Technology Acceptance Model (TAM, Davis
              1989)</strong>.
          </p>
        </div>
      </div>
    </section>

    <!-- Systems Section -->
    <section id="systems">
      <div class="section-header">
        <div class="section-eyebrow">Thesis Deliverables</div>
        <h2 class="section-title">The Three Developed Systems</h2>
        <p class="section-desc">
          Direct portal access and administrative command centers for each research application.
        </p>
      </div>

      <div class="systems-container">
        <?php foreach ($systems as $sys): ?>
          <article id="<?= h($sys['id']) ?>" class="system-card <?= h($sys['accent']) ?>-accent">
            <div class="system-grid">
              <!-- Left: Info & Launch -->
              <div>
                <div class="system-header">
                  <span class="system-chip <?= h($sys['accent']) ?>"><?= h($sys['shortTitle']) ?></span>
                  <span class="system-num"><?= h($sys['index']) ?></span>
                </div>

                <h3 class="system-title"><?= h($sys['officialTitle']) ?></h3>
                <p class="system-summary"><?= h($sys['summary']) ?></p>

                <div class="system-meta-box">
                  <div class="meta-row">
                    <span class="meta-label">Client / Partner:</span>
                    <span class="meta-val"><?= h($sys['client']) ?> (<?= h($sys['clientNote']) ?>)</span>
                  </div>
                  <div class="meta-row">
                    <span class="meta-label">Architecture:</span>
                    <span class="meta-val"><?= h($sys['framework']) ?></span>
                  </div>
                  <div class="meta-row">
                    <span class="meta-label">Locale:</span>
                    <span class="meta-val"><?= h($sys['locale']) ?></span>
                  </div>
                </div>

                <div class="system-actions">
                  <a href="<?= h($sys['portalUrl']) ?>" class="btn-launch <?= h($sys['accent']) ?>" target="_blank">
                    🚀 Launch System Portal
                  </a>
                  <a href="<?= h($sys['adminUrl']) ?>" class="btn-admin" target="_blank">
                    🔐 Admin Login Portal
                  </a>
                </div>
              </div>

              <!-- Right: Functional Domains -->
              <div>
                <div class="domains-title">Functional Domains & Specifications</div>
                <div class="domains-grid">
                  <?php foreach ($sys['domains'] as $dom): ?>
                    <div class="domain-card">
                      <h5><?= h($dom['title']) ?></h5>
                      <p><?= h($dom['desc']) ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Framework Section -->
    <section id="framework">
      <div class="section-header">
        <div class="section-eyebrow">Research Rigor</div>
        <h2 class="section-title">Theoretical & Technical Framework</h2>
        <p class="section-desc">Grounding the evaluation in established academic instruments.</p>
      </div>

      <div class="grid-2">
        <div class="info-card">
          <div style="font-size: 24px; margin-bottom: 12px;">📊</div>
          <h3>Technology Acceptance Model (TAM)</h3>
          <p style="margin-bottom: 12px;"><strong>Davis, 1989</strong></p>
          <p>
            Assessing <em>Perceived Usefulness (PU)</em> and <em>Perceived Ease of Use (PEOU)</em> among system
            administrators and target end-users to validate adoption viability.
          </p>
        </div>

        <div class="info-card">
          <div style="font-size: 24px; margin-bottom: 12px;">📐</div>
          <h3>ISO/IEC 25010 Standard</h3>
          <p style="margin-bottom: 12px;"><strong>Software Quality Evaluation</strong></p>
          <p>
            Structured instrument evaluating three primary quality dimensions: Usability, Functional Efficiency, and
            System Security across each developed platform.
          </p>
        </div>
      </div>
    </section>

    <!-- Proponents Section -->
    <section id="proponents" style="margin-bottom: 100px;">
      <div class="section-header">
        <div class="section-eyebrow">Investigative Team</div>
        <h2 class="section-title">Research Proponents & Advisory Panel</h2>
      </div>

      <div style="margin-bottom: 32px;">
        <h4
          style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted); margin-bottom: 16px;">
          Research Proponents
        </h4>
        <div class="proponents-grid">
          <?php foreach ($proponents as $p): ?>
            <div class="person-card">
              <div class="person-avatar"><?= h($p['initials']) ?></div>
              <h4><?= h($p['name']) ?></h4>
              <p><?= h($p['role']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <h4
          style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--muted); margin-bottom: 16px;">
          Advisory Panel
        </h4>
        <div class="proponents-grid">
          <?php foreach ($panel as $p): ?>
            <div class="person-card">
              <div class="person-avatar"
                style="color:#fbbf24; border-color:rgba(251,191,36,0.3); background:rgba(251,191,36,0.1);">
                <?= h($p['initials']) ?></div>
              <h4><?= h($p['name']) ?></h4>
              <p><?= h($p['role']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer>
    <div class="container">
      <p style="color: #fff; font-weight: 700;">Immaculada Concepcion College · College of Computer Studies</p>
      <p>Center for Research, Innovation, and Development (CRID) · AY 2025–2026</p>
      <p style="font-size: 12px; margin-top: 12px; opacity: 0.6;">© <?= date('Y') ?> Capstone Research Portfolio. All
        rights reserved.</p>
    </div>
  </footer>

</body>

</html>