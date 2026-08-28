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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css">
  <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=ClanOT&display=swap" rel="stylesheet">
  <style>
    body,
    html {
      height: 100%;
      margin: 0;
      font-family: 'ClanOT', sans-serif;
    }

    .navbar-custom {
      background: transparent;
      border-bottom: 5px solid black !important;
      /* Add a border line below the navbar */
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

    .section-white {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 85%;
      text-align: center;
      padding: 50px 20px;
      background-image: url('assets/Ribbon-1.png');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .section-white p {
      color: black;
      font-size: 14px;
      margin-bottom: 5px;
    }

    .section-white h1 {
      text-transform: uppercase;
      margin-bottom: 20px;
      font-weight: bold;
      font-size: 75px;
    }

    .description {
      margin: 0px 50px 40px 50px;
    }

    .description h4 {
      font-size: 25px;
      font-family: 'ClanOT', sans-serif;
      font-weight: 300;
      line-height: 38.40px;
      text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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

    /* Add margin to container */
    .container-margin {
      margin-left: 20px;
      margin-right: 20px;
      margin-bottom: 20px;
    }

    .section-background {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 50px 20px;
      background-image: url('assets/img/1.png');
      background-size: contain;
      background-position: center;
      background-repeat: no-repeat;
      height: auto;
      min-height: 95vh;
      /* Maintain minimum height for larger screens */
    }

    @media (max-width: 768px) {
      .section-background {
        padding: 30px 15px;
        /* Reduce padding for smaller screens */
      }
    }

    @media (max-width: 576px) {
      .section-background {
        padding: 20px 10px;
        /* Further reduce padding for extra small screens */
      }
    }
    /* font 32 */
    .font32{
      font-size: 32px !important;
    } .section-white1 {
      background-color: white;
      text-align: center;
      padding: 50px 20px;
    }

    .section-white1 h1 {
      text-transform: uppercase;
      margin-bottom: 20px;
      font-weight: bold;
    }
  </style>
</head>

<body>
  <?php include "includes/nav.php" ?>

  <div class="section-white1">
  <h1 style="background-color: #FCD420; border-radius: 22px; display: inline-block; padding: 10px 100px; font-style:none; margin-top: 50px;" >
      <span>THE MUSEUM</span>
    </h1>
  
    <h3 style="font-size: 32px; font-family: 'ClanOT', sans-serif; font-weight: 500; line-height: 38.40px; ">
      Welcome to Our 360 Virtual Museum: Dive into History, Culture, and Pride
    </h3>

    <div class="description">
      <h4>Get ready for a fun, interactive 360° tour of National University’s heritage! Dive into the history, explore
        iconic spots, and uncover cool stories that make our campus legendary. Have fun exploring!</h4>
    </div>
  </div>

  <div class="container-margin">
    <iframe src="tour.php" style="width: 100%; height: 1500px; border: none;"></iframe>
  </div>


  <div class="section-white">
        <h1 class="font72">MUSEUM HOURS</p>
          <P class="font32" >Open Monday - Saturday</P>
          <p class="font32">9:00 am to 4:00 pm</p>
  </div>

  <?php include "includes/footer.php" ?>

  <!-- Owl Carousel JavaScript -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

</body>

</html>