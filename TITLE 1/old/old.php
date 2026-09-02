<?php
session_start();
require_once("admin/connection.php");

// Function to update artwork type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  // Update type action
  if ($action === 'update_type' && isset($_POST['id'], $_POST['type'])) {
    $id = $_POST['id'];
    $type = $_POST['type'];

    // Update type in database
    $sqlUpdate = "UPDATE artworks SET type = :type WHERE id = :id";
    $stmt = $pdo->prepare($sqlUpdate);
    $stmt->execute(['type' => $type, 'id' => $id]);

    // Return success message if needed
    echo json_encode(['status' => 'success', 'message' => 'Type updated successfully.']);
    exit;
  }

  // Delete artwork action
  if ($action === 'delete' && isset($_POST['id'])) {
    $id = $_POST['id'];

    // Perform delete operation
    $sqlDelete = "DELETE FROM artworks WHERE id = :id";
    $stmt = $pdo->prepare($sqlDelete);
    $stmt->execute(['id' => $id]);

    // Return success message if needed
    echo json_encode(['status' => 'success', 'message' => 'Artwork deleted successfully.']);
    exit;
  }
}

// Fetch artworks from database
$sql = "SELECT * FROM artworks where type='Featured'";
$stmt = $pdo->query($sql);
$artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>National University Museum</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <!-- Owl Carousel CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
  <style>
    body,
    html {
      height: 100%;
      margin: 0;
      font-family: 'Inter', sans-serif;
      overflow-x: hidden;
    }

    .bg-cover {
      background: url('assets/img/image1.jpg') no-repeat center center;
      background-size: cover;
      height: 100%;
      position: relative;
    }

    .navbar-custom {
      background: transparent;
    }

    .navbar-nav {
      margin: auto;
      margin-left: 400px;
    }

    @media (max-width: 1200px) {
      .navbar-nav {
        margin-left: 2dvh;
      }
    }

    @media (max-width: 992px) {
      .navbar-nav {
        margin-left: 100px;
      }
    }

    @media (max-width: 768px) {
      .navbar-nav {
        margin-left: 100px;
      }
    }

    @media (max-width: 576px) {
      .navbar-nav {
        margin-left: 50px;
      }
    }

    @media (max-width: 400px) {
      .navbar-nav {
        margin-left: 0;
      }
    }

    .nav-link {
      text-transform: uppercase;
      font-weight: bold;
      color: white !important;
    }

    .navbar-brand {
      color: #384E9C !important;
    }

    .form-inline .input-group {
      position: relative;
    }

    .form-inline .input-group input {
      padding-left: 2.5rem;
    }

    .form-inline .input-group .fa-search {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: gray;
    }

    .container-box {
      position: absolute;
      bottom: 20px;
      right: 20px;
      background-color: rgba(252, 212, 32, 0.9);
      padding: 20px;
      height: 50%;
      border-radius: 10px;
      max-width: 500px;
    }

    .container-box p {
      color: white;
      font-style: italic;
    }

    .container-box h3 {
      font-weight: bold;
      margin-top: 0;
      color: white;
    }

    .section-white {
      background-color: white;
      padding: 50px 0;
    }

    .section-gray {
      background-color: #f7f7f7;
      padding: 50px 0;
    }

    /* Scrollbar styles */
    ::-webkit-scrollbar {
      width: 0px;
    }

    ::-webkit-scrollbar-thumb {
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(0, 0, 0, 0.3);
    }

    .blue {
      color: #384E9C;
    }
  </style>
</head>

<body>
  <?php include "includes/nav.php" ?>
  <div class="bg-cover">
    <h1>
      WELCOME TO<br>
      NATIONAL UNIVERSITY<br>
      MUSEUM
    </h1>
    <div class="container-box">
      <h3 style="text-transform: uppercase; font-weight: 900;"></h3>
      <p></p>
      <a href="#" class="btn btn-light"></a>
    </div>
  </div>

  <style>
    .bg-cover {
      display: flex;
      justify-content: flex-start;
      /* Aligns content to the left horizontally */
      align-items: center;
      /* Centers content vertically */
      height: 100vh;
      /* Full viewport height */
      background-color: #f8f9fa;
      /* Replace with your background color or image */
      padding-left: 20px;
      /* Optional: Adds padding from the left edge */
    }

    h1 {
      font-size: 3.5rem;
      text-align: left;
      /* Ensures the text is aligned to the left */
    }
  </style>


  <!-- Section 1 -->
  <div class="section-gray">
    <div class="container">
      <div style="position: relative; text-align: left;">
        <p style="color: gray; font-size: 14px; margin-bottom: 5px;">01 / VIRTUAL TOUR</p>
        <h1 style="text-transform: uppercase; margin-bottom: 20px; font-weight: bolder;">View the Museum</h1>
        <img src="assets/img/visit.png" alt="Image Description"
          style="max-width: 100%; height: auto; display: block; margin: 0 auto;">
        <div
          style="background-color: rgba(147, 139, 155, 0.5); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); padding: 20px; text-align: center;">
          <img src="assets/img/logo.jpg" width="150" height="100" alt="Logo" style="display: block; margin: 0 auto;"
            class="logo">
          <p style="color: white; margin-top: 10px;">
            Experience National University Museum like never before with our engaging virtual tour. Explore fascinating
            exhibits, uncover rich history, and discover groundbreaking research—all from the comfort of your screen.
          </p>
          <a href="vtour.php" class="btn btn-light">Visit Museum</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Section 2 -->
  <div class="section-gray">
    <div class="container">
      <div style="text-align: center;">
        <p style="color: gray; font-size: 14px; margin-bottom: 5px;">02 / EXHIBIT</p>
        <h1 style="text-transform: uppercase; margin-bottom: 20px; font-weight: bold;">IMMERSE IN CREATIVITY</h1>

        <div class="owl-carousel owl-theme">
          <!-- Image 1 -->
          <?php foreach ($artworks as $artwork): ?>
            <div class="item">
              <img
                src="3D/exhibit/<?php echo htmlspecialchars($artwork['artist_id']); ?>/<?php echo htmlspecialchars($artwork['img']); ?>"
                alt="Image 1" class="hover-effect" style="width: 500px; height: 350px; object-fit: cover;">
              <style>
                .hover-effect {
                  transition: transform 0.3s ease, opacity 0.3s ease;
                }

                .hover-effect:hover {
                  transform: scale(1.05);
                  /* Slightly zoom in the image */
                  opacity: 0.8;
                  /* Slightly fade the image */
                }
              </style>
              <br>
              <div class="description">
                <a href="exhibit.php"><?php echo htmlspecialchars($artwork['artist_name']); ?><br><?php echo htmlspecialchars($artwork['title']); ?>
                  [
                  <?php
                  $dateString = $artwork['date'];
                  $formattedDate = date('F j, Y', strtotime($dateString));
                  echo htmlspecialchars($formattedDate);
                  ?>
                  ]
                </a>
              </div>
            </div>
          <?php endforeach; ?>
          <!-- Image 2 -->

        </div>


      </div>
    </div>
  </div>


  <!-- Section 3 -->
  <div class="section-gray" style="background-color: #1D1B20;">
    <div class="container">
      <div class="row">
        <!-- Left Part -->
        <div class="col-lg-8">
          <div style="position: relative; text-align: left; color: white;">
            <h1 style="text-transform: uppercase; margin-bottom: 20px; font-weight: bolder; font-size: 75px;">Call
              for<br> Entries!</h1>
            <div style="color: white;">
              <p><strong>Attention Nationalians!</strong></p>
              <p>The NU Art Award is back, inviting entries from creatives who dare to push the boundaries of artistic
                expression. Whether you wield a brush, sculpt with light, or harness the power of digital mediums, this
                is your chance to showcase your talent on a prestigious platform.</p>
              <p>As champions of innovation, we welcome submissions from emerging talents and seasoned professionals
                alike. From traditional masterpieces to groundbreaking digital installations, we celebrate the diverse
                spectrum of artistic endeavors.</p>
              <p>Selected works will not only have the opportunity to captivate audiences but also vie for a range of
                coveted prizes and recognition. Join us in shaping the future of art and making your mark in the vibrant
                tapestry of creative expression.</p>
            </div>
          </div>
        </div>
        <!-- Right Container Box -->
        <div class="col-lg-4 position-relative">
          <!-- Arrow Image -->
          <img style="margin-top: 250px;" src="assets/img/arrow.png" alt="Arrow Image" class="arrow-img">
          <!-- Container Box -->
          <div class="container-box"
            style="background-color: rgba(255, 255, 255, 0.9); color: black; max-width: 500px; bottom: 0; right: 20px; position: absolute;">
            <h5 style="margin: 0;">SUBMIT ENTRIES HERE:</h5>
            <a href="exhibitor.php" style="text-decoration: underline;">Submit Entries Link</a>
            <h6 style="margin-top: 10px;">ON OR BEFORE 15 August 2024, 11:59pm</h6>
          </div>
        </div>
      </div>
    </div>
  </div>

  <style>
    /* Additional CSS styles */
    .arrow-img {
      position: absolute;
      top: -30px;
      /* Adjust vertical positioning */
      left: 50%;
      /* Center horizontally */
      transform: translateX(-50%);
    }
  </style>


  <!-- Section 4 -->
  <div class="section-white">
    <div class="container">
      <div class="row">
        <!-- Left Part -->
        <div class="col-lg-8">
          <div style="position: relative; text-align: left; color: #1D1B20;">
            <h1 style="text-transform: uppercase; margin-bottom: 20px; font-weight: bold;">Know More About Us</h1>
            <p style="font-style: italic;">Welcome to NU Museum, where tradition meets innovation to ignite creativity
              and exploration. Our digital sanctuary not only honors the heritage of our school but also showcases a
              vibrant tapestry of contemporary art. From interactive installations to virtual reality experiences, we
              celebrate the evolving expressions of our community's artistic spirit.</p>
            <p style="font-style: italic;">We proudly amplify the voices of local artists, providing a platform for
              their unique perspectives and visions. Through student outreach programs and collaborative initiatives, we
              nurture the next generation of talent, blending their innovative works with those of established artists.
            </p>
            <p style="font-style: italic;">Join us at NU Museum for a journey through our school's rich heritage and
              forward-thinking creativity. Step into the future of art appreciation and discovery.</p>
            <a href="about.php" class="btn btn-light">Read More</a>
          </div>
        </div>
        <!-- Right Part with Image -->
        <div class="col-lg-4 position-relative">
          <img src="assets/img/visit.png" alt="Visit NU Museum" class="img-fluid"
            style="position: absolute; right: -30%; top: 0; height: 100%; border-top-left-radius: 250px; border-bottom-left-radius: 250px;">

        </div>
      </div>
    </div>
  </div>
  <?php include "includes/footer.php" ?>

  <style>
    .logo img {
      width: 60px;
      /* Adjusted size for the logo */
      height: 60px;
      /* Adjusted size for the logo */
      margin-bottom: 10px;
      /* Optional: Adds some space below the logo */
    }
  </style>
  <script src="../assets/js/jquery.min.js"></script>

  <script src="../assets/js/popper.min.js"></script>
  <script src="../assets/js/bootstrap.bundle.min.js"></script>

  <script>document.addEventListener('DOMContentLoaded', function () {
      // Define an array of images with titles, descriptions, links, and button text
      var images = [
        {
          title: 'Welcome to <br><span class="blue">National University</span> <br>Museum',
          description: 'Explore an immersive online collection dedicated to celebrating the vibrant history and accomplishments of our educational community. Through interactive exhibits, curated galleries, and multimedia presentations, this virtual museum brings to life the stories, achievements, and cultural milestones that define our school\'s legacy.',
          link: 'story.php',
          buttonText: 'Read More',
          image: 'assets/img/image1.jpg' // Add the image URL here
        },
        {
          title: 'Experience the <br><span class="blue">360</span> Virtual Museum',
          description: 'Step into our 360 Virtual Museum showcasing the rich heritage of our school. Explore immersive exhibits and artifacts that tell the story of our educational journey, past achievements, and cultural milestones. This interactive experience invites you to delve into history and discover the legacy that shapes our institution today.',
          link: 'vtour.php',
          buttonText: 'Visit Museum',
          image: 'assets/img/image2.jpg' // Add the image URL here
        },
        {
          title: 'WALK WITH PRIDE',
          description: '<span style="font-weight: bolder;" class="blue">VISIT OUR MUSEUM IN<br> CAMILO OSIAS HALL</span>',
          link: 'exhibit.php',
          buttonText: 'View Exhibit',
          image: 'assets/img/image3.jpg' // Add the image URL here
        }
      ];

      var currentIndex = 0;
      var intervalId;

      // Function to change background image and update content
      function changeBackground() {
        // Change background image
        document.querySelector('.bg-cover').style.backgroundImage = `url('${images[currentIndex].image}')`;

        // Update content based on current image
        document.querySelector('.container-box h3').innerHTML = images[currentIndex].title; // Use innerHTML to allow HTML formatting
        document.querySelector('.container-box p').innerHTML = images[currentIndex].description; // Use innerHTML to allow HTML formatting
        document.querySelector('.container-box a').setAttribute('href', images[currentIndex].link);
        document.querySelector('.container-box a').textContent = images[currentIndex].buttonText;

        currentIndex = (currentIndex + 1) % images.length; // Cycle through images
      }

      // Initialize with the details of the first slide
      changeBackground();

      // Start changing background image every 1.5 seconds (1500ms)
      intervalId = setInterval(changeBackground, 4000);
    });

  </script>
  <!-- Owl Carousel JavaScript -->
  <script>
    $(document).ready(function () {
      $(".owl-carousel").owlCarousel({
        loop: true,
        margin: 20,
        responsiveClass: true,
        responsive: {
          0: {
            items: 1,
            nav: false
          },
          600: {
            items: 2,
            nav: false
          },
          1000: {
            items: 2,
            nav: false,
            loop: false
          }
        }
      });
    });

  </script>
</body>

</html>