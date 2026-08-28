


<link href="https://fonts.googleapis.com/css2?family=ClanOT&display=swap" rel="stylesheet">
<style>
  :root {
    --title: 36px;
    --semititle: 18px;
  }

  p,
  h1 {
    margin: 0;
  }

  body {
    margin: 0;
    font-family: 'ClanOT', sans-serif;
  }

  html {
    scroll-behavior: smooth;
  }



  .hero-section {
    background-color: #D9D9D9;
    display: flex;
  }
  
  


  /* MOBILE */
  @media (max-width: 500px) {
    .news>.title {
      padding: 1rem;
      font-size: 24px !important;
      text-align: center;
      color: #fff;
      background-color: #384E9C;
    }


    .news-container {
      width: 100%;
      overflow-x: scroll;
    }

    /* MOBILE - HEADER */

    header {
      flex-direction: column;
      padding: 1rem;
      gap: 1rem;
      color: #fff;
    }

    .navigator {
      display: flex;
      flex-direction: row;
      width: 100%;
      gap: 2rem;
      order: 3;
      overflow-x: scroll;
    }

    .navigator>a {
      text-wrap: nowrap;
    }

    .brand {
      width: 20%;
      order: 1;
    }

    .search-field {
      order: 2;
    }

    /* MOBILE - HERO */

    .hero-section {
      flex-direction: column;
    }

    .hero-greet>.title {
      padding: 1rem 0;
      font-size: 24px !important;
      text-align: center;
      font-weight: 500;
    }

    .hh {
      flex: 1;
      /* height: 500px; */
      width: 100%;
    }

    .about>img {
      width: 300px;
      left: -75px;
    }

    .about {
      gap: 1rem;
      margin-top: 2rem;
      margin-bottom: 1rem;
    }

    .know {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      align-items: center;
    }

    .know>.about-NU {
      padding: 0 1rem;
      font-size: 14px;
      text-align: justify;
      font-style: italic;
    }

    .know>.title {
      font-weight: bold;
      font-size: 26px !important;
    }

    .quotes {
      display: grid;
      place-items: center;
      text-align: center;
      margin: 1rem 0;
    }


    .quotes>div {
      padding: 1rem 0;

      display: flex;
      flex-direction: column;
      gap: 1rem;
      width: 100%;
      background-color: #FCD420;
    }

    .quotes>div>p:first-child {

      font-size: 18px;
    }

    .quotes>div>p:last-child {
      font-size: 16px;
    }

    /* MOBILE - ABOUT */

    .know>img {
      width: 40%;
    }

    .know>button {
      width: 50%;
    }

    /* MOBILE - CONTACT */
    .contact {
      flex-direction: column;
      padding-top: 2rem;
    }
    .contact-form textarea {
        width: 100%; /* Set the textarea to take the full width of its container */
        max-width: 100%; /* Prevents it from expanding beyond the container */
        height: 150px; /* Adjust height as desired */
        padding: 10px; /* Adds some padding inside the textarea */
        font-size: 16px; /* Adjust font size if needed */
        resize: vertical; /* Allows vertical resizing only */
        box-sizing: border-box; /* Ensures padding is included in the width calculation */
    }

    .contact-form {
      padding: 1rem;
    }

    .contact-detail {
      padding: 0 1rem;
      display: flex;
      flex-direction: column;
      color: #fff;
      margin-bottom: 2rem;

    }

    .contact-detail>p:first-of-type {
      margin-bottom: 1rem;
      color: #000 !important;
      font-weight: 500 !important;
      
    }


    .contact-detail>p>span {
      color: #FCD420 !important;
     
    }

    .contact-detail>img {
      width: 73px;
      height: 79px;
      object-fit: cover;
      margin-bottom: 2rem;
    }

    .contact-form>textarea {
      height: 200px;
      resize: none;
    }

    .contact-form>button {
      align-self: flex-end;
      width: 50%;
    }

    /* MOBILE - EXHIBIT */
    .exhibit {
      padding: 2rem 0;
      background-color: #384E9C;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      align-items: center;
    }

    .exhibit>.title {
      color: #FCD420;
    }

    .exhibit>.semi-title {
      color: #fff;
      text-align: center;
    }

    .exhibit-container {
      padding: 1rem;
      gap: 1rem;
      height: 100%;
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: center;
    }

    .exh-title {
      font-style: italic;
      color: #FCD420 !important;
      font-weight: 500;
      font-size: 16px;
    }

    .exh>img {
      width: inherit;
      height: auto;
      object-fit: cover;
    }

    .exhibit>button {
      width: 50%;
    }

    .exh {
      overflow-y: auto;
      overflow-x: hidden;
      height: 350px;
      max-width: 300px;
      color: #fff;
      gap: 1rem;
      display: flex;
      flex-direction: column;
      width: 100%;
      align-items: center;
    }

    .exh-description,
    .exh>p:first-of-type {
      font-size: 12px;
    }

    .exh-description {
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 7;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* MOBILE - NEWS */
    .news-item {
      padding: 0.5rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .news-item>img {
      height: 150px;
      width: 150px;
      object-fit: cover;
    }

    .news-item>p {
      font-size: 14px;
      font-weight: 500
    }

    .news-container {
      display: flex;
      flex-direction: row;
    }

    .news {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    /* MOBILE - VIRTUAL TOUR */
    .vtour {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      padding-top: 2rem;
    }

    .vtour-greet {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .vtour-greet>p:first-of-type {
      text-align: right;
      background-color: #384E9C;
      padding-right: 1rem;
      color: #fff !important;
      border-top-right-radius: 5px;
      border-bottom-right-radius: 5px;
    }

    .vtour-greet>p {
      color: #384E9C;
      margin: 0;
      font-size: 24px;
      padding-top: 1rem;
      padding-bottom: 1rem;
    }

    .vtour-description {
      border-top-left-radius: 5px;
      border-bottom-left-radius: 5px;
      font-size: 12px;
      align-self: flex-end;
      width: 80%;
      text-align: center;
      padding: 1rem;
      background-color: #FCD420;
    }

    .vtour-images {
      display: flex;
      flex: row;
      position: relative;
      overflow: scroll;
      overflow-y: hidden;
      flex: 1;
    }

    .vtour-images>img {
      height: 300px;
      width: auto;
      object-fit: cover;
    }

    /* MOBILE - SEARCH BOX */
    .search-field {
      display: flex;
      flex-direction: row;
      align-items: center;
      background-color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 2px;
    }

    .search-field>img {
      width: 12px;
      height: 12px;
      margin-right: 0.5rem;
    }

    .search-field>input {
      outline: none;
      background-color: none;
      border: none;
    }

  }

  button {
    font-size: 14px;
    padding: 1rem;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    background-color: #D9D9D9;
  }

  .contact-form {

    background-color: #FCD420;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .contact {
    display: flex;
    background-color: #384E9C;
  }



  .article {
    margin-top: auto;
    text-align: center;
    padding: 0.5rem;
    background-color: #384E9C;
    color: #fff;
  }


  header {
    display: flex;
    background-color: #384E9C;
  } .contact {
      height: 106vh ;
      flex-direction: row;
      padding: 7rem 5rem 3rem 5rem !important;
    }.contact-form {
      margin-left: auto;
      padding: 2rem;
    }
  /* DESKTOP */
  @media (min-width: 1440px) {
    .contact {
      height: 75vh ;
      flex-direction: row;
      padding: 7rem 5rem 3rem 5rem !important;
    }
    /* DESKTOP - HEADER */
    header {
      flex-direction: row;
      align-items: center;
    }

    .navigator {
      margin-left: auto;
      flex-direction: row;
      gap: 2.5rem;
      margin-right: 2.5rem;
    }

    /* DESKTOP - HERO */

    .hero-greet>.title {
      margin-right: 1rem;
      width: 600px;
      padding: 1rem 0;
      font-size: 54px !important;
      text-align: left;
    }

    /* DESKTOP - EXHIBIT */

    .exhibit {
      background-color: #384E9C;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      padding-top: 5vh !important;
      height: 170vh !important;
      align-items: center;
    }

    @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@100&display=swap');

    .exhibit>.title {
      font-size: 72px;
      font-weight: 200;
      line-height: 86.40px;
      color: #fff;

    }

    .exhibit>.semi-title {
      color: #fff;
    }



    .exh-title {
      font-style: italic;
      color: #FCD420 !important;
      font-weight: 500;
      font-size: 28px;
    }

    .exh>img {
      width: 90%;
      height: auto;
      object-fit: cover;
    }

    .exhibit>button {
      margin-top: 50px;
      background: #D9D9D9;
      border-radius: 8px;
      width: 25%;
      padding: 1rem;
      font-size: 18px;
      border-radius: 5px;
      outline: none;
      border: none;
      cursor: pointer;
      background-color: #D9D9D9;
    }

    .exh {
      overflow-y: auto;
      overflow-x: hidden;
      height: 100%;
      max-width: 500px;
      color: #fff;
      gap: 1rem;
      display: flex;
      flex-direction: column;
      width: 100%;
      align-items: center;
    }

    .exh-description {
      font-size: 14px;
    }

    /* MOBILE - ABOUT */

    .know>img {
      position: absolute;
      left: -130%;
      width: 100%;
      z-index: -1;
    }

    .know>button {
      align-self: flex-end;
      font-size: 16px;
      width: 25%;
      padding: 1rem;
      border-radius: 5px;
      border: none;
      cursor: pointer;
      background-color: #D9D9D9;
    }


    .know>.about-NU {
      font-size: 20px;
      font-weight: 500;
      font-style: italic;
    }

    .know>.title {
      font-weight: bold;
      font-size: 48px !important;
    }

    .know {
      position: absolute;
      right: 0;
      margin-right: 2rem;
      width: 50%;
      text-align: right;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .about {
      padding-top: 15vh !important;
      height: 95vh !important;
      gap: 1rem;
    }

    section {
      height: 90vh;
    }

    .contact-form {
      margin-left: auto;
      padding: 2rem;
    }

    
    /* DESKTOP - VIRTUAL TOUR */
    .vtour {
      display: flex;
      flex-direction: column;
      gap: 2rem;
      padding-top: 5vh !important;
      height: 125vh !important;
    }

    .vtour-greet {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
    }

    .vtour-greet>p:first-of-type {


      color: white;
      font-size: 72px;
      line-height: 86.40px;
      text-align: right;
      background-color: #384E9C;
      padding-right: 1rem;
      color: #fff !important;
      border-top-right-radius: 5px;
      border-bottom-right-radius: 5px;
    }

    .vtour-greet>p {


      color: white;
      font-size: 72px;
      line-height: 86.40px;
      color: #384E9C;
      margin: 0;
      padding-top: 1rem;
      padding-bottom: 1rem;
    }

    .vtour-description {

      color: white;
      font-size: 72px;
      line-height: 86.40px;
      text-align: right;
      color: black;
      font-size: 32px;
      font-weight: 200;
      line-height: 38.40px;
      border-top-left-radius: 5px;
      border-bottom-left-radius: 5px;
      align-self: flex-end;
      width: 70%;
      padding: 1rem;
      background-color: #FCD420;
    }

    /* DESKTOP - NEWS */
    .news {
      align-items: center;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .news-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      /* Changed to 2 columns */
      gap: 2rem;
    }


    .news {
      padding-top: 5vh !important;
      height: 95vh !important;
    }

    .news>.title {
      width: 90%;
      background-color: #384E9C;
      border-radius: 22px;
      color: #fff;
      padding: 1rem 2rem;
    }

    .news-item>p {
      font-size: var(--semititle);
      font-weight: 500
    }

    .news-item {
      max-width: 350px;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .news-item>img {
      height: 350px;
      width: 350px;
      object-fit: cover;
    }


    .about>img {
      width: 700px;
      left: -200px;
    }

    .hero-section {
      flex-direction: row;
      justify-content: center;
      align-items: center;
    }

    .burger {
      display: none;
    }

    /* DESKTOP - HEADER */

    header {
      flex-direction: row;
      align-items: center;
      height: 10vh;
      padding: 0 2rem;
    }

    .navigator {
      display: flex;
    }

    form {
      width: 25%;
    }

    form>input {
      width: 100%;
      padding: 0.35rem 1rem;
    }

    .brand {
      width: 10%;
    }

    .hh {
      height: 500px;
      width: 500px;
    }

    .hh>div>h2 {
      z-index: 2;
      position: absolute;
    }

    .quotes {
      padding: 2rem !important;
      height: 95vh !important;
      display: grid;
      place-items: center;
    }

    .quotes>div {
      padding: 2rem;
      display: grid;
      place-items: center;
      height: 500px;
      background-color: #FCD420;
    }

    .quotes>div>p:first-child {
      margin: 100px;
      text-align: center;
      color: black;
      font-size: 32px;
      font-style: italic;
      font-weight: 200;
      line-height: 38.40px;

      font-size: var(--title);
      text-align: center;
    }

    .quotes>div>p:last-child {
      font-size: var(--semititle);
      text-align: center;
    }

    .about>img {
      margin-top: 4rem;
      position: absolute;
      height: auto;

      z-index: -1;
    }

    .contact-detail {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      color: #fff;
      font-size:24px;
    }

    .contact-detail>p:nth-child(1) {
      color: #000 !important;
      font-weight: 500 !important;
    }


    .contact-detail>p>span {
      color: #FCD420 !important;
    }

    .contact-detail>img {
      width: 73px;
      height: 79px;
      object-fit: cover;
      margin-bottom: auto;
    }

    .contact-form>textarea {
      height: 400px;
      resize: none;
    }

    .contact-form>button {
      align-self: flex-end;
      font-size: 16px;
      padding: 0.5rem 1rem;
      border-radius: 5px;
      border: none;
      cursor: pointer;
      background-color: #D9D9D9;
    }

    /* DESKTOP - SEARCH BOX */

    .search-field {
      display: flex;
      flex-direction: row;
      align-items: center;
      background-color: #fff;
      padding: 0.5rem 1rem;
      border-radius: 2px;
    }

    .search-field>img {
      width: 12px;
      height: 12px;
      margin-right: 0.5rem;
    }

    .search-field>input {
      outline: none;
      background-color: none;
      border: none;
    }
  }



  a {
    text-decoration: none;
  }

  .title {
    border-radius: 22px;
  }

  .semi-title {
    font-size: var(--semititle) !important;
  }

  .navigator>a {
    color: #fff;
  }

  /* Slick Overrides */
  .slick-slide {
    height: 500px !important;
  }

  .slick-slide>img {
    width: inherit !important;
    height: inherit !important;
    object-fit: cover !important;

  }

  .slick-next::before,
  .slick-prev::before {
    font-size: 30px !important;

  }

  .slick-next,
  .slick-prev {
    width: 40px !important;
  }

  .slick-next {
    right: 15px !important;
  }

  .slick-prev {
    left: 15px !important;
    z-index: 1 !important;
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
  font-size: 31px;
  color: white;
  line-height:0.8;
}.logo-text-sm{
  font-weight: bold;
  font-size: 20px;
  color: white;

}

</style>

<!-- <section class="contact">
  <br>
  <br>
  <br>
  <div class="contact-detail">
    
  <div class="logo-and-text">
      <img src="assets/NU.png" alt="NATIONAL University Logo" class="imagsize">
      <p class="logo-text">NATIONAL UNIVERSITY <br> <span class="logo-text-sm">Legacy Office</span></p>
    </div>
    <p>Museum hours : <span>9:00am - 4:00pm</span></p>
    <p>551 M.F. Jhocson St. Sampaloc, Manila, PH 1008</p>
    <p>+6328712-1900</p>
    <p>info@national-edu.ph</p>
    <p style="color: black; font-size:29px;">© 2024 National University. All rights reserved.</p>
  </div>
  <div class="contact-form">
    <h1 style="margin: 0 300px 0 0;">Share Your <br>Thoughts <br>With Us...</h1>
    <div class="textarea-wrapper" style="position: relative;">
    <textarea style="font-size:29px; width: 100%; height: 200px; resize: none;">Inquiry/Comment/...</textarea>
    <button style="position: absolute; right: 10px; bottom: 20px; width:30%; height:auto" >Submit</button>
  </div>
  </div>
  
</section> -->


<section class="contact-section">
  <div class="contact-details">
    <div class="logo-and-text">
      <img src="assets/NU.png" alt="National University Logo" class="logo">
      <p class="university-name">NATIONAL UNIVERSITY<br><span class="legacy-office">Legacy Office</span></p>
    </div>
    <p class="museum-hours">Museum Hours: <span>9:00 am - 4:00 pm</span></p>
    <p>551 M.F. Jhocson St. Sampaloc, Manila, PH 1008</p>
    <p>+632 8712-1900</p>
    <p>info@national-u.edu.ph</p>
    <p class="rights">© 2024 National University. All rights reserved.</p>
  </div>
 
  <div class="contact-form">
  <h1>Share Your <br> Thoughts <br> With Us...</h1>
  <?php
  // Check if the success parameter is in the URL
  if (isset($_GET['success']) && $_GET['success'] == 1) {
      echo '<script>
              window.addEventListener("load", function() {
                  alert("Thank you! Your thoughts have been submitted successfully.");
              });
            </script>';
  }
  ?>
  <form method="POST" action="thoughts.php">
    <div class="textarea-wrapper">
      <textarea name="thoughts" placeholder="Inquiry/comment/request"></textarea>
      <button type="submit">Submit</button>
    </div>
  </form>
</div>



  <style>
    /* Styling for the entire contact section */
    .contact-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-start; /* Align items to start */
      padding: 40px 60px;
      background-color: #384E9C;
      color: white;
      height: auto; /* Auto height */
    }

    /* Left side - contact details */
    .contact-details {
      width: 50%;
    }

    .logo-and-text {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
    }

    .logo {
      width: 90px;
      margin-right: 10px;
    }

    .university-name {
      font-size: 32px;
      font-weight: bold;
      line-height: 1.2;
    }

    .legacy-office {
      font-size: 22px;
    }

    .museum-hours span {
      color: #FEC32F;
    }

    p {
      font-size: 20px;
      margin-bottom: 10px;
    }

    .rights {
      margin-top: 40px;
      font-size: 18px;
      color: black;
    }

 /* Styling for the entire contact section */
.contact-form {
  min-width: 500px; /* Increased width to make the form area larger */
  height: 400;
  background-color: #FEC32F;
  padding: 20px; /* Added padding to give some space inside the form */
  border-radius: 10px;
  color: black;
  box-sizing: border-box;
}

.contact-form h1 {
  font-size: 30px;
  margin-bottom: 20px;
}

.textarea-wrapper {
  position: relative;
}

.contact-form textarea {
  min-width: 450px; /* Increased width to make the form area larger */
  height: 300px;
  padding: 10px;
  font-size: 16px;
  border-radius: 8px;
  border: none;
  margin-bottom: 20px;
  box-sizing: border-box; /* Ensures padding doesn’t affect width */
}

.contact-form button {
  position: absolute;
  left: 340px;
  bottom: 40px;
  height: 40px; /* Adjust height for better match with textarea */
  padding: 5px 10px; /* Adjust padding for proper alignment */
  width: 100px; /* Adjust width if needed */
  background-color: #D3D3D3; /* Light grey background */
  color: #000000; /* Pure black text */
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 16px; /* Slightly larger font size */
  font-weight: bold; /* Makes the text bolder */
}

.contact-form button:hover {
  background-color: #A9A9A9; /* Darker grey for hover effect */
  color: #000000; /* Keep text black on hover */
}


    /* Responsive styling */
    @media (max-width: 768px) {
      .contact-section {
        flex-direction: column;
        align-items: center;
      }

      .contact-details, .contact-form {
        width: 100%;
        margin-bottom: 20px;
      }

      .contact-form textarea {
        height: 200px;
      }
    }
  </style>
</section>
