<?php
// Start the session
session_start();

// Include the database connection file
require_once("admin/connection.php");

// Fetch artworks with the type "Featured" from the database
$sql = "SELECT * FROM artworks WHERE type = 'Featured'";
$stmt = $pdo->query($sql);
$artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NU Museum</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
    integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/slick-js/slick.css">
  <link rel="stylesheet" href="assets/slick-js/slick-theme.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <!-- Owl Carousel CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
  <link rel="stylesheet" href="../assets/css/pannellum.min.css">
  <link href="https://fonts.googleapis.com/css2?family=ClanOT&display=swap" rel="stylesheet">
  <style>
    .hero-section {
      width: 100%;
      height: 100vh;
      background-image: url("assets/img/carousel/1.png");
      background-size: cover;
      background-repeat: no-repeat;
      background-position: center;
      position: relative;
      transition: background-image 1s ease-in-out;
      justify-content: flex-end;
    }
  .hero-image {
      position: absolute;
      right: 50px;
      top: 50%;
      transform: translateY(-50%);
      max-width: 100%;
      z-index: 1;
    }


    .hero-greet {
      position: relative;
      z-index: 2;
      /* Ensure it's above the image */
      text-align: center;
      color: white;
    }

    /* Controller Dots */
    .controller-dots {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 10px;
    }

    .controller-dots span {
      display: block;
      width: 15px;
      height: 15px;
      background-color: white;
      border-radius: 50%;
      cursor: pointer;
    }

    .controller-dots span.active {
      background-color: gray;
    }.logo-and-text {
  display: flex;
  align-items: center; 
}

.logo-and-text img {
  margin-right: 10px; 
  width: 73px;
  height:79px;
}

.logo-text {
  font-weight: bold;
  font-size: 31px !important;
  color: white;
  line-height:0.8;
}.contact-detail{
  
  font-size: 24px;
  color: white;
}.navbar-custom {
      background: transparent;
      border-bottom: 5px solid black !important;
    }

    .nav-link {
      text-transform: uppercase;
      font-weight: bold;
      color: white !important;
    }

    .navbar-brand {
      color: #384E9C !important;
    }
  </style>

  <script>
    $(document).ready(function () {
      const images = [
        'assets/img/carousel/1.png',
        'assets/img/carousel/2.png',
        'assets/img/carousel/3.png',
        'assets/img/carousel/4.png',
        'assets/img/carousel/5.png'
      ];
      let currentIndex = 0;

      function updateHeroBackground(index) {
        $('#heroSection').css('background-image', 'url(' + images[index] + ')');
        $('.controller-dots span').removeClass('active');
        $('.controller-dots span').eq(index).addClass('active');
      }

      setInterval(function () {
        currentIndex = (currentIndex + 1) % images.length;
        updateHeroBackground(currentIndex);
      }, 4000);  // 4 seconds interval

      // Dots click event
      $('.controller-dots span').click(function () {
        currentIndex = $(this).index();
        updateHeroBackground(currentIndex);
      });
    });
  </script>
</head>

<body>
<?php include "includes/nav.php" ?>

  <section class="hero-section" id="heroSection">
    <img src="assets/img/hcad2.png" alt="Hero Image" class="hero-image"> 
    <!-- Controller Dots -->
    <div class="controller-dots">
      <span class="active"></span>
      <span></span>
      <span></span>
      <span></span>
      <span></span>
    </div>
  </section>

  <style>
    .vtourimage {
      background-repeat: no-repeat;
      background-position: bottom;
      background-size: contain;
    }
  </style>
  <section class="vtour" id="virtual_tour">
    <div class="vtour-greet">
      <p>RE IMAGINE</p>
      <p>HISTORY.</p>

    </div>
    <div class="vtour-description">
      Step into the past and explore our school's legacy through an immersive VR journey, where history comes to life
      in every detail.
    </div>
    <img class="vtourimage" src="assets/1.png">

  </section>

  <br>
  <br>
  <br>

  <!-- <button class="seemore">Visit Museum</button> -->
  <a href="vtour.php" target="_blank">
    <button class="seemore">Visit Museum</button>
</a>

  

<style>

.seemore {
  margin: 60px auto 50px auto;  /* Centers the button horizontally */
  background-color: #1E3A8A; /* Blue background */
  color: white; /* White text */
  box-shadow: 0px 8px 15px rgba(0, 0, 0, 0.2); /* Soft shadow */
  border-radius: 25px; /* Curved corners */
  width: 200px; /* Adjust width */
  padding: 1rem 2rem; /* Increase padding for better appearance */
  font-size: 22px; /* Larger font size for better readability */
  font-family: 'ClanOT', sans-serif; /* Use a sans-serif font */
  text-align: center; /* Center the text */
  border: none; /* Remove default border */
  cursor: pointer; /* Pointer cursor on hover */
  transition: transform 0.3s ease, box-shadow 0.3s ease; /* Smooth transition for hover effects */
}

  .seemore:hover {
    transform: translateY(-3px); /* Slight raise effect on hover */
    box-shadow: 0px 12px 20px rgba(0, 0, 0, 0.3); /* Enhanced shadow on hover */
  }

</style>






<section class="exhibit" id="exhibits">
  <h1 class="title">EXHIBITS</h1>
  <p class="semi-title">Witness the boundless creativity of our students come to life.</p>
  <hr style="width: 50%; height:3px; border: none; background-color: white;">

  <div class="exhibit-container slider">
    <div class="slides">
      <?php foreach ($artworks as $artwork): ?>
        <?php if (strtolower($artwork['type']) === 'featured'): ?>
          <div class="slide" onclick="window.location.href='3d/?id=<?php echo htmlspecialchars($artwork['artist_id']); ?>'">
            <img
                src="3D/exhibit/<?php echo htmlspecialchars($artwork['artist_id']); ?>/<?php echo htmlspecialchars($artwork['img']); ?>"
                alt="<?php echo htmlspecialchars($artwork['artist_name']); ?>" class="hover-effect">
            <br>
            <p class="exh-date">
              <?php echo date('F j, Y', strtotime($artwork['date'])) . " / " . htmlspecialchars($artwork['artist_name']); ?>
            </p>
            <p class="tatakpinoy"><?php echo htmlspecialchars($artwork['title']); ?></p>
            <p class="exh-description"><?php echo htmlspecialchars($artwork['description']); ?></p>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Navigation Buttons -->
    <button class="prev" onclick="moveSlide(-1)">&#10094;</button>
    <button class="next" onclick="moveSlide(1)">&#10095;</button>
  </div>

  <div style="height: 90px;"></div>
  <div class="button-container">
    <button class="see-more-button" onclick="window.location.href='exhibit.php'">See More Exhibits</button>
  </div>
</section>


<!-- Slider CSS -->
<style>
/* Container to hold the entire slider */
.exhibit-container {
  position: relative;
  overflow: hidden;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* Container for slides */
.slides {
  display: flex; /* Align slides in a row */
  transition: transform 0.5s ease;
  width: calc(200%); /* Adjust for 2 images at a time */
  gap: 5px;
}

.slide {
  flex: 0 0 50%; /* Take up 50% of the width for two images */
  max-width: 50%; /* Ensure it doesn't expand beyond this */
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative; /* Required for ::before or ::after positioning */
}

/* Add a divider between slides */
.slide:not(:last-child)::after {
  content: ""; /* Add a visual element */
  position: absolute;
  top: 0;
  right: 0; /* Align to the right edge of the slide */
  width: 2px; /* Thickness of the line */
  height: 100%; /* Full height of the slide */
  background-color: white; /* Match your design */
}

/* Divider between two images */
.slide + .slide::before {
  content: "";
  display: block;
  width: 2px;
  background-color: white;
  height: 100%;
  position: absolute;
  left: -1px;
}

/* Slide image styling */
.slide img {
  width: 100%; /* Ensure images fit their container */
  height: 350px; /* Fixed height */
  object-fit: cover; /* Maintain aspect ratio */
  border-radius: 5px; /* Optional rounded corners */
}

/* Navigation Buttons */
.prev, .next {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background-color: rgba(0, 0, 0, 0.5);
  color: white;
  border: none;
  font-size: 18px;
  padding: 10px;
  cursor: pointer;
  z-index: 1;
}

.prev {
  left: 10px;
}

.next {
  right: 10px;
}

.prev:hover, .next:hover {
  background-color: rgba(0, 0, 0, 0.7);
}
</style>
<!-- Slider JavaScript -->
<script>
const slides = document.querySelector('.slides');
const slideCount = document.querySelectorAll('.slide').length; // Total slides
let currentIndex = 0;

function moveSlide(direction) {
  const visibleSlides = 2; // Number of slides visible at a time
  const maxIndex = Math.ceil(slideCount / visibleSlides) - 1; // Last set of slides
  currentIndex = Math.max(0, Math.min(maxIndex, currentIndex + direction)); // Clamp index
  const offset = -currentIndex * 100; // Move by 100% for each set of slides
  slides.style.transform = `translateX(${offset}%)`;
}

// Optional: Auto-slide every 5 seconds
// setInterval(() => moveSlide(1), 5000);

</script>



    <style>
  
.exhibit-container {
  display: flex;
  justify-content: space-around;
  align-items: center;
  flex-wrap: wrap; /* Ensure items wrap on smaller screens */
  /* margin-bottom: 40px;
  height: cover;
   */
}

.exh {
  width: 45%;
  max-width: 400px; /* Limit max width for each exhibit */
  overflow: hidden;
  text-align: center;
  padding: 20px; /* Add some padding for spacing */
  box-sizing: border-box; /* Ensure padding is included in width */
}




.tatakpinoy {
  color: #FEC32F; /* Bright yellow */
  font-size: 16px;
  font-family: 'ClanOT', sans-serif;
  font-weight: 600;
  text-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
  margin-bottom: 10px;
   text-align: center;
}

.exh-date,
.exh-description {
  font-family: 'ClanOT', sans-serif;
  font-size: 18px;
  color: white;
  text-align: center;
  line-height: 1.5;
  margin-bottom: 10px;
}

.divider {
  width: 1px;
  height: 100%;
  background-color: white;
  margin: 0 20px;
}
.see-more-button {
  background-color: #FEC32F;
  padding: 10px 50px;
  border: none;
  border-radius: 10px;
  font-size: 18px;
  cursor: pointer;
  /* margin: 0 auto; /* Center the button */
  /* display: block; Ensure the button is centered */ 
  box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
  transition: background-color 0.3s ease;
}
.see-more-button1 {
  background-color: #1E3A8A;
  padding: 10px 50px;
  border: none;
  border-radius: 10px;
  font-size: 18px;
  cursor: pointer;
  /* margin: 0 auto; /* Center the button */
  /* display: block; Ensure the button is centered */ 
  box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
  transition: background-color 0.3s ease;
}



/* Responsive Layout */
@media (max-width: 268px) {
  .exhibit-container {
    flex-direction: column;
    align-items: center;
  }

  .exh {
    width: 80%;
    margin-bottom: 40px;
  }

  .divider {
    display: none;
  }
}

@media (max-width: 576px) {
  .tatakpinoy {
    font-size: 28px;
  }

  .exh-date,
  .exh-description {
    font-size: 16px;
  }


}
.button-container {
    text-align: center;
}
.button-container1 {
    text-align: right;
    /* background-color:  #1E3A8A; */
}
    </style>
<br>


  </section>
  <section class="quotes">
    <div>
      <p>Success is not just a goodluck, it’s a cobination of hard work, good credit standing, opportunity, readiness
        and timing. </p>
      <p>— Henry Sy, Sr | Founder, SM Group</p>
    </div>
  </section>
  <section class="news" id="news">
    <p class="title">MUSEUM NEWS AND EVENTS</p>
    <div class="news-container">
      <div class="news-item">
        <img src="assets/images/news.png" alt="">
        <p>Pagdiriwang sa Kalye Jhocson UAAP Season 86</p>
        <a style="text-decoration: underline; " href="https://www.facebook.com/NationalUniversityPhilippines/posts/in-case-you-missed-it-here-are-the-highlights-from-pagdiriwang-sa-kalye-jhocson-/853510023477803/"
        >In Case You Missed It! During... - National University Philippines |
          Facebook</a>
      </div>

      <div class="news-item">
        <img src="assets/images/news2.png" alt="">
        <p>NSSU Delegation Explores NU's Legacy During MOA Signing</p>
        <a style="text-decoration: underline;"  href="https://www.facebook.com/NationalUniversityPhilippines/posts/in-case-you-missed-it-here-are-the-highlights-from-pagdiriwang-sa-kalye-jhocson-/853510023477803/"
        >In Case You Missed It! During... - National University Philippines |
          Facebook</a>
        
      </div>
    </div>
  </section>

  <style>
    .title {
      height: 100px;
      font-size: 72px;
      font-weight: 200;
      line-height: 86.40px;
      text-align: center;
      margin-bottom: 20px;
      /* Add space below the title */
    }
    @media(max-width: 1440px){
      .about {
        padding-top: 30vh !important;
        height: 95vh !important;
        gap: 1rem;
    }
    }
    
    @media (max-width: 1440px) {
    .contact {
        height: 75vh !important;
        flex-direction: row;
        padding: 1rem 5rem !important;
        margin-top: 20%;
    }
}@media(min-width:1441px){
  .contact {
        height: 75vh !important;
        flex-direction: row;
        padding: 1rem 5rem !important;
        margin-top:0;
    }
}

  </style>
   <div style="height: 150px;"></div>

  <section class="about" id="about">
    <div class="know">
      <img src="assets/images/dogge.jpg" alt="">
      <h1 class="title" style = "color : #35408E">KNOW MORE ABOUT US</h1>
      <p class="about-NU">Welcome to NU Museum, where tradition meets innovation to ignite creativity and exploration.
        Our digital sanctuary not only honors the heritage of our school but also showcases a vibrant tapestry of
        contemporary art. From interactive installations to virtual reality experiences, we celebrate the evolving
        expressions of our community's artistic spirit.
        <br><br>We proudly amplify the voices of local artists, providing a platform for their unique perspectives and
        visions. Through student outreach programs and collaborative initiatives, we nurture the next generation of
        talent, blending their innovative works with those of established artists.
        <br><br>Join us at NU Museum for a journey through our school's rich heritage and forward-thinking creativity.
        Step into the future of art appreciation and discovery.
      </p>
      <div class="button-container1">
    <button class="see-more-button" onclick="window.location.href='about.php'">Read More</button>
</div>
    </div>
  </section>
  <div style="height: 450px;"></div>

  <?php include "includes/footer.php" ?>

  <script src="assets/slick-js/slick.js"></script>
  <script src="assets/script.js"></script>
</body>

</html>