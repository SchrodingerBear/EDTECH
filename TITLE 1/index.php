<?php
require_once __DIR__ . '/includes/bootstrap.php';

$hero = $landing['hero_image_path'];
$email = $landing['contact_email'];
$login = $landing['login_url'];
$company = $landing['company_name'];
$featureLoop = array_merge($features, $features);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title><?= h($company) ?> | Bring Your Campus to Life</title>
  <meta name="description" content="AI-assisted AR and 360° virtual campus tours for forward-thinking schools.">
  <link rel="icon" href="public/icon-light-32x32.png" media="(prefers-color-scheme: light)">
  <link rel="icon" href="public/icon-dark-32x32.png" media="(prefers-color-scheme: dark)">
  <link rel="icon" href="public/icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="public/apple-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800;900&display=swap">
  <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
<main id="top" class="page">
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="logo" href="#top" aria-label="<?= h($company) ?> home">
        <?php if (!empty($landing['logo_path'])): ?>
          <img class="logo-mark" src="<?= h($landing['logo_path']) ?>" alt="">
        <?php else: ?>
          <span class="logo-mark"><?= icon('move3d') ?></span>
        <?php endif; ?>
        <span>Innovatech <span class="accent">PH</span></span>
      </a>

      <nav class="nav-desktop" aria-label="Primary">
        <a href="#product">Product</a>
        <a href="#campuses">Partner Campuses</a>
        <a href="#how">How it works</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
      </nav>

      <div class="header-actions">
        <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch theme">
          <span data-icon-sun class="is-hidden"><?= icon('sun', 17) ?></span>
          <span data-icon-moon><?= icon('moon', 17) ?></span>
        </button>
        <a class="btn btn-primary" href="<?= h($login) ?>">Login Portal <?= icon('arrow-up-right', 15) ?></a>
      </div>

      <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
        <?= icon('menu') ?>
      </button>
    </div>

    <nav class="nav-mobile wrap" id="mobile-nav" aria-label="Mobile">
      <a href="#product" data-close-menu>Product</a>
      <a href="#campuses" data-close-menu>Partner Campuses</a>
      <a href="#how" data-close-menu>How it works</a>
      <a href="#about" data-close-menu>About</a>
      <a href="#contact" data-close-menu>Contact</a>
      <div class="nav-mobile-actions">
        <button type="button" class="btn btn-ghost theme-toggle" data-theme-toggle>
          <span data-icon-sun class="is-hidden"><?= icon('sun', 16) ?></span>
          <span data-icon-moon><?= icon('moon', 16) ?></span>
          <span data-theme-label>Dark mode</span>
        </button>
        <a class="btn btn-primary" href="<?= h($login) ?>" data-close-menu>Login Portal</a>
      </div>
    </nav>
  </header>

  <section class="wrap hero">
    <div class="hero-blob" aria-hidden="true"></div>
    <div>
      <div class="pill"><?= icon('sparkles', 14) ?> AI-powered virtual campus</div>
      <h1><?= h($landing['hero_headline'] ?? 'Explore. Experience. Innovate.') ?></h1>
      <p class="lede"><?= h($landing['hero_sub'] ?? 'Make a great first impression, at any scale. Give every prospective student a real sense of belonging before they even set foot on campus.') ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="#campuses">Start virtual tour <?= icon('arrow-right', 16) ?></a>
        <a class="btn btn-ghost" href="#how">How it works</a>
      </div>
    </div>
    <div class="hero-card">
      <img src="<?= h($hero) ?>" alt="Innovatech virtual campus experience">
      <div class="hero-caption">
        <div>
          <p class="kicker">360° campus view</p>
          <p><strong>Explore every place that matters.</strong></p>
        </div>
        <div class="play"><?= icon('play', 18) ?></div>
      </div>
    </div>
  </section>

  <section class="stats">
    <div class="wrap stats-grid">
      <?php
      $statsDefault = [['24+','Partner schools'],['180+','Tours created'],['4.8k','Places described by AI'],['100%','AR-ready platform']];
      $statsData = $landing['stats'] ?? $statsDefault;
      foreach ($statsData as [$sv, $sl]): ?>
        <div><p class="stat-value"><?= h($sv) ?></p><p class="stat-label"><?= h($sl) ?></p></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="product" class="wrap section">
    <div class="section-intro">
      <p class="eyebrow">One platform, more possibilities</p>
      <h2 class="section-title">Everything a campus tour should be.</h2>
      <p class="lede">From the first visit to the final enrollment decision, give your community a better way to experience your school.</p>
    </div>
    <div class="marquee-wrap">
      <div class="marquee-track">
        <?php foreach ($featureLoop as $item): [$title, $text, $iconName, $featureImg] = array_pad($item, 4, $hero); ?>
          <article class="feature-card">
            <div class="feature-media">
              <img src="<?= h($featureImg ?: $hero) ?>" alt="<?= h($title) ?>">
              <div class="feature-icon"><?= icon($iconName) ?></div>
            </div>
            <div class="feature-body">
              <h3><?= h($title) ?></h3>
              <p><?= h($text) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="campuses" class="campuses section">
    <div class="wrap">
      <div class="campuses-head">
        <div>
          <p class="eyebrow">The Innovatech directory</p>
          <h2>Explore our partner campuses.</h2>
          <p class="lede">Step inside schools that are opening their doors to the next generation of learners.</p>
        </div>
        <div class="campus-tools">
          <label class="search">
            <span class="is-hidden">Search campuses</span>
            <?= icon('search', 17) ?>
            <input id="campus-search" type="search" placeholder="Search campuses" autocomplete="off">
          </label>
          <div class="filters" role="group" aria-label="Campus type">
            <button type="button" class="is-active" data-filter="All">All</button>
            <button type="button" data-filter="University">University</button>
            <button type="button" data-filter="College">College</button>
          </div>
        </div>
      </div>
      <div class="campus-grid" id="campus-grid"></div>
    </div>
  </section>

  <section id="how" class="wrap section">
    <div class="how-grid">
      <div>
        <p class="eyebrow">From idea to impact</p>
        <h2 class="section-title">Launch your campus in four simple steps.</h2>
        <p class="lede">No expensive scanning crews. No complicated rollout. Just a faster, smarter way to open your doors.</p>
      </div>
      <div class="steps">
        <?php foreach ($steps as [$num, $title, $desc]): ?>
          <div class="step">
            <span class="step-num"><?= h($num) ?></span>
            <div>
              <h3><?= h($title) ?></h3>
              <p><?= h($desc) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php 
  $whyBg = $landing['why_bg'] ?? ''; 
  $whyStyle = $whyBg ? "background: linear-gradient(rgba(11,13,22,0.82), rgba(11,13,22,0.92)), url('" . h(url($whyBg)) . "') center/cover no-repeat; color: #fff;" : '';
  
  $quoteBg = $landing['quote_bg'] ?? '';
  $quoteStyle = $quoteBg ? "background: linear-gradient(rgba(11,13,22,0.78), rgba(11,13,22,0.88)), url('" . h(url($quoteBg)) . "') center/cover no-repeat; color: #fff; border: 1px solid rgba(255,255,255,0.18);" : '';
  ?>
  <section id="about" class="about section" style="<?= $whyStyle ?>">
    <div class="wrap about-grid">
      <div style="<?= $whyBg ? 'color: #fff;' : '' ?>">
        <p class="eyebrow" style="<?= $whyBg ? 'color: var(--cyan-300);' : '' ?>">Why schools choose Innovatech</p>
        <h2 class="section-title" style="<?= $whyBg ? 'color: #fff;' : '' ?>">Make a great first impression, at any scale.</h2>
        <div class="why-grid">
          <?php foreach ($why as [$title, $desc]): ?>
            <div>
              <h3 style="<?= $whyBg ? 'color: #fff;' : '' ?>"><?= h($title) ?></h3>
              <p style="<?= $whyBg ? 'color: rgba(255,255,255,0.8);' : '' ?>"><?= h($desc) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="quote-card" style="<?= $quoteStyle ?>">
        <?= icon('message', 30) ?>
        <blockquote><?= h($landing['quote'] ?? '"Innovatech helps us give prospective students a real sense of belonging before they even set foot on campus."') ?></blockquote>
        <div class="quote-person">
          <?php
          $qname = $landing['quote_name'] ?? 'Maria Angela Reyes';
          $initials = implode('', array_filter(array_map(fn($w) => strtoupper($w[0] ?? ''), explode(' ', $qname))));
          ?>
          <div class="avatar"><?= h(substr($initials, 0, 2)) ?></div>
          <div>
            <p><strong><?= h($qname) ?></strong></p>
            <p class="role"><?= h($landing['quote_role'] ?? 'Director of Admissions · Partner school') ?></p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="contact" class="wrap section">
    <?php 
    $ctaBg = $landing['cta_bg'] ?? ''; 
    $ctaStyle = $ctaBg ? "background: linear-gradient(rgba(11,13,22,0.8), rgba(11,13,22,0.92)), url('" . h(url($ctaBg)) . "') center/cover no-repeat; color: #fff; border: 1px solid rgba(255,255,255,0.18);" : '';
    ?>
    <div class="cta-box" style="<?= $ctaStyle ?>">
      <div class="cta-inner">
        <div>
          <p class="eyebrow" style="<?= $ctaBg ? 'color: var(--cyan-300);' : '' ?>"><?= h($landing['cta']['headline'] ?? 'Your next chapter starts here') ?></p>
          <h2 style="<?= $ctaBg ? 'color: #fff;' : '' ?>">Bring your campus online.</h2>
          <p class="lede" style="<?= $ctaBg ? 'color: rgba(255,255,255,0.85);' : '' ?>"><?= h($landing['cta']['sub'] ?? 'Talk to our team about creating an experience your students will remember.') ?></p>
        </div>
        <a class="btn btn-cyan" href="mailto:<?= h($email) ?>"><?= h($landing['cta']['btn_text'] ?? 'Contact support') ?> <?= icon('arrow-up-right', 16) ?></a>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="wrap footer-inner">
      <a class="logo" href="#top">
        <span class="logo-mark"><?= icon('move3d') ?></span>
        <span>Innovatech <span class="accent">PH</span></span>
      </a>
      <div class="footer-links">
        <a href="#product">Product</a>
        <a href="#campuses">Campuses</a>
        <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>
      </div>
      <p class="copy">&copy; 2020 <?= h($company) ?></p>
    </div>
  </footer>
</main>

<div class="modal" id="tour-modal" role="dialog" aria-modal="true" aria-labelledby="modal-campus-name">
  <div class="modal-card">
    <div class="modal-media">
      <img id="modal-campus-img" src="<?= h($hero) ?>" alt="">
      <div class="modal-overlay-copy">
        <div>
          <div class="play lg"><?= icon('play', 24) ?></div>
          <p><strong>Interactive tour preview</strong></p>
          <p id="modal-campus-hint" class="muted" style="color:#fff9">Drag to look around</p>
        </div>
      </div>
      <button type="button" class="modal-close" data-close-modal aria-label="Close tour preview"><?= icon('x', 18) ?></button>
    </div>
    <div class="modal-foot">
      <div>
        <p>Live campus tour</p>
        <h3 id="modal-campus-name"></h3>
      </div>
      <button type="button" class="btn btn-primary" data-close-modal>Close preview</button>
    </div>
  </div>
</div>

<script>
  window.CAMPUS_DATA = <?= json_encode($campuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/landing.js"></script>
</body>
</html>
