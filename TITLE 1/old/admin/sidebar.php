
<nav class="sb-sidenav accordion sb-sidenav-dark bg-secondary" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Main</div>
                            <a class="nav-link" href="dashboard.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                                Dashboard
                            </a>
                        
                            <div class="sb-sidenav-menu-heading">Manage</div>
                            <a class="nav-link" href="artworks.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-chart-area"></i></div>
                                Exhibit
                            </a>
                            <a class="nav-link" href="thoughts.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-comment"></i></div>
                                Thoughts
                            </a>
                            <a class="nav-link" href="news.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-newspaper"></i></div>
                                News
                            </a>
                            <a class="nav-link" href="exhibitor.php">
                                <div class="sb-nav-link-icon"><i class="fas fa-paint-brush"></i></div>
                                Exhibitor
                            </a>


                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Logged in as: <?php echo $_SESSION["username"]?></div>
                    </div>
                </nav>