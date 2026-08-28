<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=ClanOT&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<style>
  body {
  padding-top: 60px; /* Add padding to prevent content from hiding behind the fixed navbar */
}
.navbar-brand img {
  width: 70px; /* Adjust width to make it smaller */
  height: 75px; /* Adjust height to make it smaller */
}
  .navbar-custom {
  width: 100%;
  color: white;
  background-color: #35408E;
  position: fixed;
  top: 0;
  z-index: 1000;
  border-bottom: none !important;
  margin-bottom: 20px; /* Add margin-bottom for spacing */
}

.navbar-toggler {
  border: none;
  color: white;
  font-size: 28px;
  cursor: pointer;
  display: none; /* Hide by default */
}

.navbar-toggler-icon {
  font-size: 30px;
  color: white;
}

.navbar-nav {
  margin: 0 50px 0 0;
  flex: 1;
  display: flex;
  align-items: start;
  justify-content: end;
}

.collapse.navbar-collapse {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.form-inline {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.navbar-text {
  color: white; /* Ensure the text is white for visibility */
  font-size: 24px; /* Adjust font size */
  font-weight: bold;
  display: inline-block;
  vertical-align: middle;
  line-height: 1.2; /* Adjust line height for better spacing */
}

@media (max-width: 992px) {
  .navbar-toggler {
    display: block; /* Show the toggler on smaller screens */
  }

  .collapse.navbar-collapse {
    display: none; /* Hide navbar items by default on smaller screens */
    width: 100%;
  }

  .navbar-toggler[aria-expanded="true"] + .navbar-collapse {
    display: flex !important;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
  }

  .navbar-nav {
    flex-direction: column;
    margin: 0;
    justify-content: flex-start;
  }

  .form-inline {
    margin-top: 10px;
    width: 100%;
  }

  .navbar-brand {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
}

@media (max-width: 576px) {
  .navbar-brand {
    display: flex;
    justify-content: space-between;
    align-items: center; /* Ensures the text and icon are vertically aligned */
    width: 100%; /* Make sure it uses the full width */
  }

  .navbar-brand img { 
    display: none !important; /* Hides the logo on small screens */
  }

  .navbar-text {
    font-size: 18px; /* Adjust text size for small screens */
    flex-grow: 1; /* Allow the text to take up the remaining space */
    margin-right: 10px; /* Add space between text and hamburger */
    display: flex;
    flex-direction: column; /* Stack "NATIONAL UNIVERSITY" and "Legacy Office" vertically */
    justify-content: center; /* Center the text vertically */
    display: none !important;
  }

  .navbar-sm {
    font-size: 14px;
  }

  .navbar-toggler {
    display: block; /* Show the toggler */
    font-size: 28px;
    align-self: center; /* Align the hamburger icon vertically with the text */
  }

  .nav-link {
    font-size: 16px;
    padding: 10px;
  }
}


</style>
<nav id="navbar" class="navbar navbar-expand-lg navbar-custom">
  <a class="navbar-brand" href="index.php">
    <img src="assets/NU.png" width="73" height="79" class="d-inline-block align-top logo navbar-brand img" alt="Logo">
    <span class="navbar-text">NATIONAL UNIVERSITY <br><span class="navbar-sm">Legacy Office</span></span>
  </a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon">&#9776;</span> <!-- Hamburger icon -->
  </button>
  
  <div class="collapse navbar-collapse" id="navbarContent">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="vtour.php">Virtual Tour</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="exhibit.php">Exhibits</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="about.php">About</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="news.php">News</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="exhibitor.php">Exhibitor</a>
      </li>
    </ul>
    <form class="form-inline my-2 my-lg-0">
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text bg-white border-0"><i class="fa fa-search"></i></span>
        </div>
        <input class="form-control mr-sm-2 border-0" type="search" placeholder="Search" aria-label="Search">
      </div>
    </form>
  </div>
</nav>
