<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="assets/pannellum.css" />
    <script type="text/javascript" src="assets/pannellum.js"></script>

    <link rel="stylesheet" href="../assets/css/slick.min.css" />
    <link rel="stylesheet" href="../assets/css/slick-theme.min.css" />
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/slick.min.js"></script>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        #panorama {
            width: 100%;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }

        /* Container for the carousel */
        .carousel-container {
            position: absolute;
            bottom: 50px;
            /* Adjust space from the bottom of the panorama */
            left: 50%;
            transform: translateX(-65%);
            width: 70%;
            /* Adjusted width */
            max-width: 900px;
            /* Adjusted max-width */
            background-color: #384E9C;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
            box-sizing: border-box;
            z-index: 2;
            text-align: center;
            margin: 0 10%;
            /* Add margin on left and right ends */
        }

        /* Slick carousel styles */
        .slick-carousel {
            position: relative;
            width: 100%;
            margin: 0 auto;
        }

        .slick-slide {
            margin: 0 10px;
            /* Add space between images */
            position: relative;
        }

        .slick-slide img {
            width: 100%;
            /* Fixed height */
            object-fit: cover;
            /* Ensure images cover the area */
            margin: 0;
            /* Remove extra margins */
        }

        .slick-slide .caption {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            text-align: center;
            padding: 5px 0;
            box-sizing: border-box;
            font-size: 14px;
        }

        .slick-prev,
        .slick-next {
            color: #000;
        }

        .slick-dots li button:before {
            color: #000;
        }

        /* Controls styling */
        #controls {
            bottom: 10px;
            width: 100%;
            text-align: center;
            z-index: 3;
        }
        #toggle-hotspot {
    width: auto; /* Let it size according to text */
    padding: 8px 16px; /* More padding for larger button */
}

        .ctrl {
            padding: 8px 12px;
            width: 40px;
            text-align: center;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            display: inline-block;
            cursor: pointer;
            border-radius: 4px;
            margin: 0 5px;
        }

        .ctrl:hover {
            background: rgba(0, 0, 0, 1);
        }

        #music-toggle {
            position: absolute;
            top: 90px;
            left: 4px;
            width: 26px;
            height: 26px;
            background-image: url(assets/img/music.svg);
            cursor: pointer;
        }

        .music-toggle-inactive {
            background-position: 0 -26px;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white;
            opacity: 0.9;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            cursor: pointer;
        }

        .overlay img {
            max-width: 200px;
            margin: 10px 0;
        }

        .scale {
            scale: 150%;
        }

        .pnlm-load-box {
            z-index: -999;
            opacity: 0;
        }

        div.pnlm-tooltip span {
            position: absolute;
            transform: translateX(-15%);
        }

        .custom-info-hotspot {
            text-align: center;
        }

        .pnlm-container {
            background: #f4f4f4;
        }

        .custom-hotspot {
            height: 100px;
            width: 100px;
            border-radius: 50%;
            background-size: cover;
            border: solid 3px white;
            cursor: pointer;
        }

        .custom-hotspot:hover {
            border-color: yellow;
        }

        div.custom-tooltip span {
            visibility: hidden;
            position: absolute;
            border-radius: 3px;
            color: white;
            white-space: nowrap;
            text-align: center;
            padding: 5px 10px;
            cursor: default;
            z-index: 1000;
        }

        div.custom-tooltip:hover span {
            visibility: visible;
        }

        div.custom-tooltip:hover span:after {
            content: '';
            position: absolute;
            width: 0;
            height: 0;
            border-width: 5px;
            border-style: solid;
            border-color: rgba(255, 255, 255, 0.8) transparent transparent transparent;
            bottom: -10px;
            left: 50%;
            margin-left: -5px;
        }

        .slick-carousel div img {
            cursor: pointer;
        }


    </style>
</head>

<body>

    <div id="panorama">
        <!-- Carousel Container -->
        <div class="carousel-container">
            <!-- Slick Carousel -->
            <div class="slick-carousel">
                <div><img id="image1" data-id="1" alt="Image 1">
                    <div class="caption">National University Museum Entrance</div>
                </div>
                <div><img id="image2" data-id="2" alt="Image 2">
                    <div class="caption">NU Bulldog</div>
                </div>
                <div><img id="image3" data-id="3" alt="Image 3">
                    <div class="caption">Museum Entrance</div>
                </div>
                <div><img id="image4" data-id="4" alt="Image 4">
                    <div class="caption">Hallway</div>
                </div>
                <div><img id="image5" data-id="5" alt="Image 5">
                    <div class="caption">Throphy</div>
                </div>
                <div><img id="image6" data-id="6" alt="Image 6">
                    <div class="caption">Museum 1</div>
                </div>
                <div><img id="image7" data-id="7" alt="Image 7">
                    <div class="caption">Museum 2</div>
                </div>
                <div><img id="image8" data-id="8" alt="Image 8">
                    <div class="caption">Museum 3</div>
                </div>
                <div><img id="image9" data-id="9" alt="Image 9">
                    <div class="caption">Museum 4</div>
                </div>
                <div><img id="image10" data-id="10" alt="Image 10">
                    <div class="caption">Museum 5</div>
                </div>
                <div><img id="image11" data-id="11" alt="Image 11">
                    <div class="caption">Museum 6</div>
                </div>
                <div><img id="image12" data-id="12" alt="Image 12">
                    <div class="caption">Museum 7</div>
                </div>
                <div><img id="image13" data-id="13" alt="Image 13">
                    <div class="caption">Museum 8</div>
                </div>
                <div><img id="image14" data-id="14" alt="Image 14">
                    <div class="caption">Museum 9</div>
                </div>
            </div>



            <!-- Carousel Controls -->
            <div id="controls">
               
                <div class="ctrl" id="pan-up">&#9650;</div>
                <div class="ctrl" id="pan-down">&#9660;</div>
                <div class="ctrl" id="pan-left">&#9664;</div>
                <div class="ctrl" id="pan-right">&#9654;</div>
                <div class="ctrl" id="zoom-in">&plus;</div>
                <div class="ctrl" id="zoom-out">&minus;</div>
                <div class="ctrl" id="fullscreen">&#x2922;</div>
                <button id="toggle-hotspot" class="ctrl">Toggle Hotspot</button>
            </div>
        </div>

        <div id="music-toggle" class="pnlm-controls pnlm-control music-toggle-inactive"></div>
        <!-- <div class="overlay" id="overlay">
            <img src="assets/img/dark-l.png" alt="Logo">
            <img class="scale" src="assets/img/rules.png" alt="Start">
        </div> -->
    </div>

    <script type="text/javascript">
        $(document).ready(function () {
            $('.slick-carousel').slick({
                infinite: true,
                slidesToShow: 3, // Number of slides visible
                slidesToScroll: 1, // Scroll one slide at a time for smoother scroll control
                autoplay: false, // Disable autoplay
                dots: true,
                arrows: true,
                appendArrows: '.carousel-container', // Append arrows to the carousel container
                swipeToSlide: true, // Allow swiping directly to any slide
                touchThreshold: 10, // Increase sensitivity of touch
                speed: 300, // Speed of the scroll
                easing: 'ease', // Easing for smooth transitions
                responsive: [
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 2
                        }
                    },
                    {
                        breakpoint: 480,
                        settings: {
                            slidesToShow: 1
                        }
                    }
                ]
            });
        });
    </script>

    <audio id="background-music" loop>
        <source src="assets/music/3.mp3" type="audio/mpeg">
        Your browser does not support the audio element.
    </audio>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const musicToggle = document.getElementById('music-toggle');
            const backgroundMusic = document.getElementById('background-music');

            musicToggle.addEventListener('click', function () {
                if (backgroundMusic.paused) {
                    backgroundMusic.play();
                    musicToggle.classList.remove('music-toggle-inactive');
                } else {
                    backgroundMusic.pause();
                    musicToggle.classList.add('music-toggle-inactive');
                }
            });
        });
    </script>


    <script>
        function isMobileOrTablet() {
            return /Mobi|Android/i.test(navigator.userAgent) || /Tablet|iPad/i.test(navigator.userAgent);
        }

        let resolutionFolder = isMobileOrTablet() ? 'small' : 'large';
        resolutionFolder = 'small';

        function getImagePath(filename) {
            return `assets/img/360/${resolutionFolder}/${filename}`;
        }

        // Array of image filenames
        const imageFilenames = [
            '1.jpg', '2.jpg', '3.jpg', '4.jpg',
            '5.jpg', '6.jpg', '7.jpg', '8.jpg',
            '9.jpg', '10.jpg', '11.jpg', '12.jpg',
            '13.jpg', '14.jpg', 'arrow_up.png', 'arrow.jpg',
            'arrow1.png', 'arrow101.png', 'arrow102.png',
        ];

        // Set the image sources and data-id attributes dynamically
        imageFilenames.forEach((filename, index) => {
            const imageElement = document.getElementById(`image${index + 1}`);
            if (imageElement) {
                imageElement.src = getImagePath(filename);
                imageElement.setAttribute('data-id', index + 1);
                // Add click event listener to change the scene
                imageElement.addEventListener('click', function () {
                    const sceneId = this.getAttribute('data-id');
                    viewer.loadScene(sceneId);
                });
            }
        });
        
       
        var viewer = pannellum.viewer('panorama', {
            "type": "equirectangular",
            "default": {
                "firstScene": "1",
                "autoLoad": true,
                "draggable": !isMobileOrTablet() ? true : false,
                "showControls": true,
                "showFullscreenCtrl": true,
                "sceneFadeDuration": !isMobileOrTablet() ? 1000 : 0,
                "orientationOnByDefault": false,
            },
            "scenes": {
                "1": {
                    "title": "National University Museum Entrance ",
                    "hfov": 300,
                    "pitch": 0,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("1.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -5,
                            "yaw": -3,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Enter Museum",
                                "imageUrl": getImagePath("2.jpg")
                            },
                            "sceneId": "2"
                        },

                    ]
                },
                "2": {
                    "title": "NU Bulldog",
                    "hfov": 200,
                    "yaw": 360,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("2.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -5,
                            "yaw": 268,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Exit Museum",
                                "imageUrl": getImagePath("1.jpg")
                            },
                            "sceneId": "1"
                        },
                        {
                            "pitch": -10,
                            "yaw": 410,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum Entrance",
                                // "imageUrl": getImagePath("3.jpg")
                            },
                            "sceneId": "3"
                        },
                        // {
                        //     "pitch": -13,
                        //     "yaw": 13,
                        //     "type": "custom",  // Use custom type
                        //     "cssClass": "custom-info-hotspot",  // New class for your custom tooltip
                        //     "createTooltipFunc": imageTooltip,  // New function for creating tooltip
                        //     "createTooltipArgs": {
                        //         "imageUrl": "assets/img/bulldog.jpg" // Path to your image
                        //     }
                        // }
                        {
                        "pitch": -13,
                        "yaw": 13,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/b1.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            "assets/img/b2.png",
                            "assets/img/b3.png",
                            "assets/img/b4.png"
                            ],
                            "description": "The NU Bulldog Statue, unveiled on May 31, 2024, is a proud symbol of our school's spirit and tradition. Reflecting the strength and resilience of our Bulldog mascot, the gold and blue statue embodies our commitment to excellence and serves as a lasting reminder of our shared values and legacy."
                        }
                        }

                    ]
                },
                "3": {
                    "title": "Museum Entrance",
                    "hfov": 200,
                    "yaw": 370,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("3.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -5,
                            "yaw": 300,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "NU Bulldog",
                                "imageUrl": getImagePath("2.jpg")
                            },
                            "sceneId": "2"
                        },
                        {
                            "pitch": -8,
                            "yaw": 450,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Hallway",
                                "imageUrl": getImagePath("4.jpg")
                            },
                            "sceneId": "4"
                        }
                        ,
                        {
                            "pitch": -15,
                            "yaw": 365,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 1",
                                "imageUrl": getImagePath("6.jpg")
                            },
                            "sceneId": "7"
                        }
                    ]
                },
                "4": {
                    "title": "Hallway",
                    "hfov": 200,
                    "yaw": 350,
                    "pitch": -10,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("4.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -11,
                            "yaw": 260,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum Entrance",
                                "imageUrl": getImagePath("3.jpg")
                            },
                            "sceneId": "3"
                        },
                        {
                            "pitch": 1,
                            "yaw": 433,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "End Hallway",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        }
                    ]
                },
                "5": {
                    "title": "Throphy",
                    "pitch": -25,
                    "hfov": 200,
                    "yaw": 205,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("5.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -15,
                            "yaw": -90,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Hallway",
                                "imageUrl": getImagePath("4.jpg")
                            },
                            "sceneId": "4"
                        },
                        {
                            "pitch": -15,
                            "yaw": 90,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "End Hallway",
                                "imageUrl": getImagePath("6.jpg")
                            },
                            "sceneId": "6"
                        },
                    ]
                },

                "6": {
                    "title": "Museum 1",
                    "hfov": 200,
                    "yaw": 250,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("6.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -25,
                            "yaw": 55,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "H",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        }
                    ]
                },
                "7": {
                    "title": "Museum 2",
                    "hfov": 200,
                    "pitch": -15,
                    "yaw": 345,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("7.jpg"),
                    "hotSpots": [
                        {
                        "pitch": -13,
                        "yaw": 155,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgRizal.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            // "assets/img/b2.png",
                            // "assets/img/b3.png",
                            // "assets/img/b4.png"
                            ],
                            "description": `Jose Rizal bust

- The Knights of Rizal donated this white colored statue to the National University in 2023 to promote the hero's advocacy for the peaceful reformation of society through education.`
                          }
                          
                        },
                        {
                        "pitch": -13,
                        "yaw": 20,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgJhoc.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            // "assets/img/b2.png",
                            // "assets/img/b3.png",
                            // "assets/img/b4.png"
                            ],
                            "description": `Don Mariano Fortunato Jhocson

- Created by National Artist Guillermo Tolentino circa 1930. It withstood the 1998 fire, obtaining a `}
                          
                        },
                        {
                        "pitch": -20,
                        "yaw": -35,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgChair.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            // "assets/img/b2.png",
                            // "assets/img/b3.png",
                            // "assets/img/b4.png"
                            ],
                            "description": `Founder's chair
- Donated by one of the granddaughters, this is an original chair from the 1900s used by the founder, Mariano Fortunato Jhocson.
`}
                          
                        },
                        {
                            "pitch": -5,
                            "yaw": 60,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum Entrance",
                                "imageUrl": getImagePath("3.jpg")
                            },
                            "sceneId": "3"
                        },
                        {
                            "pitch": -15,
                            "yaw": 125,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 4",
                                "imageUrl": getImagePath("9.jpg")
                            },
                            "sceneId": "9"
                        },
                        {
                            "pitch": -15,
                            "yaw": 95,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 1",
                                "imageUrl": getImagePath("1.jpg")
                            },
                            "sceneId": "6"
                        },
                        {
                            "pitch": -15,
                            "yaw": 205,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 3",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "pitch": -49.32,
                            "yaw": -176.53,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                        {
                            "pitch": -21.54,
                            "yaw": -95.81,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "pitch": -8.61,
                            "yaw": -97.69,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "pitch": -7.34,
                            "yaw": -128.15,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "pitch": -8.92,
                            "yaw": 119.9,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        }
                        
                    ]
                },

                "8": {
                    "title": "Museum 3",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 270,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("8.jpg"),
                    "hotSpots": [
                        {
                            //rizal
                        "pitch": -13,
                        "yaw": -75,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgRizal.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            // "assets/img/b2.png",
                            // "assets/img/b3.png",
                            // "assets/img/b4.png"
                            ],
                            "description": `Jose Rizal bust

- The Knights of Rizal donated this white colored statue to the National University in 2023 to promote the hero's advocacy for the peaceful reformation of society through education.`
                          }
                          
                        },
                        {
                            // TELESCOPE
                       "pitch": -13,
                        "yaw": -25,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTele.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astronomical telescope

- The Astronomical telescope could magnify 1,000 times. Donated by Engineering faculty in 1950, it was housed in the NU dome atop the 5th floor of the Jhocson Memorial Building.`
                          }
                          
                        },
                        {
                            //Vballs
                       "pitch": -13,
                        "yaw": -45,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgVball.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Championship balls
- The historic Men's basketball used during the UAAP Season 77 championship.`}
                          
                        },
                        {
                            "pitch": -8,
                            "yaw": 150,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum Entrance",
                                "imageUrl": getImagePath("3.jpg")
                            },
                            "sceneId": "3"
                        },
                        {
                            "pitch": -11,
                            "yaw": 168,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 1",
                                "imageUrl": getImagePath("6.jpg")
                            },
                            "sceneId": "6"
                        }
                        ,
                        {
                            "pitch": -7,
                            "yaw": 115,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 2",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },

                        {
                            "pitch": -25,
                            "yaw": 185,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 4",
                                "imageUrl": getImagePath("9.jpg")
                            },
                            "sceneId": "9"
                        },
                        {
                            "pitch": -10.81,
                            "yaw": 62.31,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "pitch": -2.67,
                            "yaw": 40.74,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "pitch": -8.56,
                            "yaw": -2.05,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "pitch": -10.76,
                            "yaw": -170.72,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        },
                        {
                            "pitch": -18.67,
                            "yaw": 155.19,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                     
                    ]
                },
                "9": {
                    "title": "Museum 4",
                    "hfov": 200,
                    "pitch": -15,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("9.jpg"),
                    "hotSpots": [
                        {
                            // Book
                        "pitch": 5,
                        "yaw": 70,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgBook.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": `1924 Bookshelves
- Bookshelves donated by Batch 1924` }
                        },
                        {
                            // tele
                       "pitch": -13,
                        "yaw": -25,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTele.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astronomical telescope

- The Astronomical telescope could magnify 1,000 times. Donated by Engineering faculty in 1950, it was housed in the NU dome atop the 5th floor of the Jhocson Memorial Building.`
                          }
                          
                        },
                        {
                            "pitch": -8,
                            "yaw": 114,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum Entrance",
                                "imageUrl": getImagePath("3.jpg")
                            },
                            "sceneId": "3"
                        },
                        {
                            "pitch": -33,
                            "yaw": 108,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 1",
                                "imageUrl": getImagePath("6.jpg")
                            },
                            "sceneId": "6"
                        }
                        ,
                        {
                            "pitch": -9,
                            "yaw": 53,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 2",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },

                        {
                            "pitch": -10,
                            "yaw": 350,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "Museum 3",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "pitch": -3.68,
                            "yaw": -3.16,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "pitch": -3.84,
                            "yaw": 18.55,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "pitch": -6.19,
                            "yaw": 23.96,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "pitch": -18.92,
                            "yaw": 35.37,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                        {
                            "pitch": -31.01,
                            "yaw": -159.52,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        }
                    ]
                },
                "10": {
                    "title": "Museum 5",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("10.jpg"),
                    "hotSpots": [
                           {
                            // Book
                        "pitch": -13,
                        "yaw": 10,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgBook.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": `1924 Bookshelves
- Bookshelves donated by Batch 1924` }
                          
                        },
                        {
                       "pitch": -13,
                        "yaw": -65,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTele.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astronomical telescope

- The Astronomical telescope could magnify 1,000 times. Donated by Engineering faculty in 1950, it was housed in the NU dome atop the 5th floor of the Jhocson Memorial Building.`
                          }
                          
                        },
                        {
                            "pitch": -10.88,
                            "yaw": -120.54,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "7",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },
                        {
                            "pitch": -3.27,
                            "yaw": -115.73,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "6",
                                "imageUrl": getImagePath("6.jpg")
                            },
                            "sceneId": "6"
                        },
                        {
                            "pitch": -10.73,
                            "yaw": 69.03,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "pitch": -6.47,
                            "yaw": -36.01,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "8",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "pitch": -3.48,
                            "yaw": 11.89,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "pitch": -8.32,
                            "yaw": -90.73,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                        {
                            "pitch": -4.76,
                            "yaw": -86.88,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        }
                    ]
                },
                "11": {
                    "title": "Museum 6",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("11.jpg"),
                    "hotSpots": [
                        {
                            "pitch": -10.46,
                            "yaw": 11.18,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "pitch": -8.62,
                            "yaw": -33.99,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "8",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "pitch": -12.82,
                            "yaw": -110.06,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "pitch": -3.24,
                            "yaw": -91.39,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "5",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        },
                        {
                            "pitch": -7.41,
                            "yaw": -92.81,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "7",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },
                        {
                            "pitch": -6.15,
                            "yaw": -71.51,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                        {
                            "pitch": -3.48,
                            "yaw": -75.53,
                            "type": "scene",
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        }

                    ]
                },
                "12": {
                    "title": "Museum 7",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("12.jpg"),
                    "hotSpots": [
                        {
                            
                            "type": "info",
                            "yaw": -5.3,
                            "pitch": -1.35,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "5",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        },
                        {
                            "type": "info",
                            "yaw": -17.92,
                            "pitch": -5.35,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "7",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },
                        {
                            "type": "info",
                            "yaw": -53.86,
                            "pitch": -8.27,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "type": "info",
                            "yaw": -92.61,
                            "pitch": -8.27,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "type": "info",
                            "yaw": 36.8,
                            "pitch": -10,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "8",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "type": "info",
                            "yaw": 14.04,
                            "pitch": -0.87,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        },
                        {
                            "type": "info",
                            "yaw": 5.89,
                            "pitch": -3.94,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        }

                    ]
                },
                "13": {
                    "title": "Museum 8",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("13.jpg"),
                    "hotSpots": [
                        {      //Vballs
                       "pitch": -20,
                        "yaw": -10,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgVball.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Championship balls
- The historic Men's basketball used during the UAAP Season 77 championship.`}
                          
                        },
                        {
                            // tran
                        "pitch": -13,
                        "yaw": -30,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTran2.png",  
                            "thumbnailUrls": [ 
                            "assets/img/bgTran1.png",
                            "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": "Transit  An instrument to measure land areas used by surveyors."
                          }
                          
                        },
                        {
                            //rizal
                        "pitch": -13,
                        "yaw": -75,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgRizal.png",  
                            "thumbnailUrls": [  // Thumbnails to display below the main image
                            // "assets/img/b2.png",
                            // "assets/img/b3.png",
                            // "assets/img/b4.png"
                            ],
                            "description": `Jose Rizal bust

- The Knights of Rizal donated this white colored statue to the National University in 2023 to promote the hero's advocacy for the peaceful reformation of society through education.`
                          }
                          
                        },
                        {
                            // tele
                       "pitch": -13,
                        "yaw": 20,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTele.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astronomical telescope

- The Astronomical telescope could magnify 1,000 times. Donated by Engineering faculty in 1950, it was housed in the NU dome atop the 5th floor of the Jhocson Memorial Building.`
                          }
                          
                        },
                        {
                            "type": "info",
                            "yaw": -159.53,
                            "pitch": -4.61,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "14",
                                "imageUrl": getImagePath("14.jpg")
                            },
                            "sceneId": "14"
                        },
                        {
                            "type": "info",
                            "yaw": 149.55,
                            "pitch": -3.47,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "5",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        },
                        {
                            "type": "info",
                            "yaw": 28.14,
                            "pitch": -4.34,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "type": "info",
                            "yaw": 20.76,
                            "pitch": -1.53,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        },
                        {
                            "type": "info",
                            "yaw": 71.04,
                            "pitch": -10.7,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "7",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },
                        {
                            "type": "info",
                            "yaw": -2.83,
                            "pitch": -2.73,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "type": "info",
                            "yaw": -23.31,
                            "pitch": -8.02,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "8",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "type": "info",
                            "yaw": -106.84,
                            "pitch": -15.64,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "9",
                                "imageUrl": getImagePath("9.jpg")
                            },
                            "sceneId": "9"
                        }

                        

                    ]
                },
                "14": {
                    "title": "Museum 9",
                    "hfov": 200,
                    "pitch": -25,
                    "yaw": 100,
                    "autoRotate": -10,
                    "type": "equirectangular",
                    "panorama": getImagePath("14.jpg"),
                    "hotSpots": [
                        {
                            // tele
                       "pitch": -13,
                        "yaw": -25,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTele.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astronomical telescope

- The Astronomical telescope could magnify 1,000 times. Donated by Engineering faculty in 1950, it was housed in the NU dome atop the 5th floor of the Jhocson Memorial Building.`
                          }
                          
                        },
                        {
                            // astro
                            "pitch": -20,
                            "yaw": -25,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgAstro.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // // "assets/img/b4.png"
                            ],
                            "description": `Astrolabe

- An ancient instrument that charts the stars and other physical heavenly bodies.`}
                          
                        },
                        {
                            // tran
                        "pitch": -13,
                        "yaw": 55,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgTran2.png",  
                            "thumbnailUrls": [ 
                            "assets/img/bgTran1.png",
                            "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": "Transit  An instrument to measure land areas used by surveyors."
                          }
                          
                        },
                        {
                            // Dental
                        "pitch": -13,
                        "yaw": 535,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgDental.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": `Dental X-ray
- The College of Dentistry, established in 1923, was one of the well-equipped programs, including this mechanical dental x-ray.`
                          }
                          
                        },
                        {
                            // Book
                        "pitch": -13,
                        "yaw": 605,
                        "type": "custom",  // Custom hotspot
                        "autoRotate": -10,
                        "cssClass": "custom-info-hotspot",  // Class for styling
                        "createTooltipFunc": imageGalleryWithDescriptionTooltip,  // Function to create the image gallery with description
                        "createTooltipArgs": {
                            "mainImageUrl": "assets/img/bgBook.png",  
                            "thumbnailUrls": [ 
                            // "assets/img/bgTran1.png",
                            // "assets/img/bgTran2.png"
                            // "assets/img/b4.png"
                            ],
                            "description": `1924 Bookshelves
- Bookshelves donated by Batch 1924` }
                          
                        },
                        {
                            "type": "info",
                            "yaw": -82.96,
                            "pitch": -8.45,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "5",
                                "imageUrl": getImagePath("5.jpg")
                            },
                            "sceneId": "5"
                        },
                        {
                            "type": "info",
                            "yaw": -158.45,
                            "pitch": -13.82,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "13",
                                "imageUrl": getImagePath("13.jpg")
                            },
                            "sceneId": "13"
                        },
                        {
                            "type": "info",
                            "yaw": 149.58,
                            "pitch": -21.78,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "9",
                                "imageUrl": getImagePath("9.jpg")
                            },
                            "sceneId": "9"
                        },
                        {
                            "type": "info",
                            "yaw": 178.09,
                            "pitch": -2.33,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "12",
                                "imageUrl": getImagePath("12.jpg")
                            },
                            "sceneId": "12"
                        },
                        {
                            "type": "info",
                            "yaw": 168.41,
                            "pitch": -8.82,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "8",
                                "imageUrl": getImagePath("8.jpg")
                            },
                            "sceneId": "8"
                        },
                        {
                            "type": "info",
                            "yaw": -153.7,
                            "pitch": -5.34,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "10",
                                "imageUrl": getImagePath("10.jpg")
                            },
                            "sceneId": "10"
                        },
                        {
                            "type": "info",
                            "yaw": -141.24,
                            "pitch": -6.7,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "7",
                                "imageUrl": getImagePath("7.jpg")
                            },
                            "sceneId": "7"
                        },
                        {
                            "type": "info",
                            "yaw": -163.48,
                            "pitch": -2.77,
                            "cssClass": "custom-hotspot",
                            "createTooltipFunc": hotspot,
                            "createTooltipArgs": {
                                "text": "11",
                                "imageUrl": getImagePath("11.jpg")
                            },
                            "sceneId": "11"
                        }

                    ]
                },
            },

        });
    


        // function imageTooltip(hotSpotDiv, args) {
        //     var infoIcon = document.createElement('img');
        //     infoIcon.src = 'assets/img/info.png';  // Path to your info.png image
        //     infoIcon.style.width = '24px';  // Adjust size as needed
        //     infoIcon.style.cursor = 'pointer';  // Change cursor to pointer on hover

        //     var img = document.createElement('img');
        //     img.src = args.imageUrl;
        //     img.style.height = 'auto';  // Maintain aspect ratio
        //     img.style.borderRadius = '5px';  // Optional: Round corners
        //     img.style.position = 'absolute';
        //     img.style.display = 'none';  // Hide image initially
        //     img.style.left = '50%';  // Center the image
        //     img.style.transform = 'translateX(-50%)';  // Center the image horizontally
        //     img.style.top = '-100px';
        //     img.style.width = '75%';
        //     hotSpotDiv.style.width = img.style.width;
        //     hotSpotDiv.style.marginLeft = -(img.offsetWidth / 2) + 'px';
        //     hotSpotDiv.style.marginTop = -img.offsetHeight - 12 + 'px';
        //     hotSpotDiv.appendChild(img);
        //     hotSpotDiv.appendChild(infoIcon);

        //     var isImageVisible = false;  // Track the visibility state

        //     // Toggle the image on click
        //     infoIcon.addEventListener('click', function (event) {
        //         event.stopPropagation();  // Prevent click event from propagating to the document
        //         isImageVisible = !isImageVisible;
        //         img.style.display = isImageVisible ? 'block' : 'none';  // Toggle image visibility
        //     });

        //     // Hide the image when clicking outside of the hotspot
        //     document.addEventListener('click', function () {
        //         if (isImageVisible) {
        //             img.style.display = 'none';
        //             isImageVisible = false;
        //         }
        //     });

        //     // Prevent click events inside the image from hiding it
        //     img.addEventListener('click', function (event) {
        //         event.stopPropagation();  // Prevent click event from propagating to the document
        //     });
        // }
        
    function imageGalleryWithDescriptionTooltip(hotSpotDiv, args) {
    // Create info icon
    var infoIcon = document.createElement('img');
    infoIcon.src = 'assets/img/info.png';  // Path to info icon
    infoIcon.style.width = '30px';  // Larger icon size
    infoIcon.style.cursor = 'pointer';  // Pointer cursor for interaction
    infoIcon.style.marginBottom = '10px';  // Spacing between icon and other elements

    // Create container for the main image, thumbnails, and description (initially hidden)
    var galleryContainer = document.createElement('div');
    galleryContainer.style.display = 'none';  // Hidden by default
    galleryContainer.style.flexDirection = 'column';  // Stack vertically
    galleryContainer.style.alignItems = 'center';  // Center alignment for all elements
    galleryContainer.style.maxWidth = '600px';  // Larger width for the tooltip
    galleryContainer.style.padding = '20px';  // Add padding for better spacing
    galleryContainer.style.overflow = 'hidden';  // Prevent overflow of the gallery
    galleryContainer.style.backgroundColor = 'rgba(54, 65, 140, 0.8)';  // Optional: white background for better contrast
    galleryContainer.style.border = '2px solid #333';  // Add border for visibility
    galleryContainer.style.borderRadius = '10px';  // Rounded corners
    galleryContainer.style.position = 'absolute';  // Position the gallery
    galleryContainer.style.left = '50%';  // Center the tooltip horizontally
    galleryContainer.style.top = '50%';  // Center the tooltip vertically
    galleryContainer.style.transform = 'translate(-50%, -50%)';  // Center the tooltip using translate

    // Create main image with slide-up animation effect
    var mainImage = document.createElement('img');
    mainImage.src = args.mainImageUrl;
    mainImage.style.width = '400px';  // Set larger main image size
    mainImage.style.height = 'auto';  // Maintain aspect ratio
    mainImage.style.borderRadius = '10px';  // Optional: rounded corners
    mainImage.style.marginBottom = '20px';  // Spacing below the main image
    mainImage.style.transition = 'transform 0.5s ease, opacity 0.5s ease';  // Slide-up and fade-out effect

    // Function to handle slide-up transition
    function slideUpImage(thumbnailUrl) {
        mainImage.style.transform = 'translateY(-100%)';  // Slide the current image up
        mainImage.style.opacity = '0';  // Fade out the current image

        setTimeout(function() {
            // After the transition, replace the image source and reset the position
            mainImage.src = thumbnailUrl;
            mainImage.style.transform = 'translateY(100%)';  // Position the new image below the container

            // Allow a slight delay to reset the transition
            setTimeout(function() {
                mainImage.style.transform = 'translateY(0)';  // Slide the new image into view
                mainImage.style.opacity = '1';  // Fade in the new image
            }, 50);  // Small delay to ensure smooth transition
        }, 500);  // Duration for the initial slide-up transition (500ms)
    }

    // Create thumbnail container
    var thumbnailContainer = document.createElement('div');
    thumbnailContainer.style.display = 'flex';  // Horizontal layout for thumbnails
    thumbnailContainer.style.gap = '15px';  // Larger spacing between thumbnails
    thumbnailContainer.style.justifyContent = 'center';  // Center the thumbnails
    thumbnailContainer.style.alignItems = 'center';  // Center align vertically
    thumbnailContainer.style.marginBottom = '20px';  // Larger spacing below thumbnails

    // Create thumbnails
    args.thumbnailUrls.forEach(function (thumbnailUrl) {
        var thumbnail = document.createElement('img');
        thumbnail.src = thumbnailUrl;
        thumbnail.style.width = '70px';  // Larger thumbnail size
        thumbnail.style.height = 'auto';  // Maintain aspect ratio
        thumbnail.style.borderRadius = '5px';
        thumbnail.style.cursor = 'pointer';  // Make thumbnail clickable
        thumbnail.style.border = '2px solid #333';  // Add border to each thumbnail
        thumbnail.style.padding = '3px';  // Optional: Add padding inside the border for spacing
        thumbnail.style.boxSizing = 'border-box';  // Include padding and border in the thumbnail's total size

        // On thumbnail click, transition the main image
        thumbnail.addEventListener('click', function () {
            slideUpImage(thumbnailUrl);  // Trigger the slide-up animation and image change
        });

        // Append thumbnail to the container
        thumbnailContainer.appendChild(thumbnail);
    });

    // Create fixed description container
    var descriptionContainer = document.createElement('div');
    descriptionContainer.style.width = '100%';  // Full width for the description
    descriptionContainer.style.maxHeight = '150px';  // Set max height to limit overflow
    descriptionContainer.style.overflowY = 'auto';  // Enable vertical scrolling if content overflows
    descriptionContainer.style.padding = '20px';  // Padding inside the description
    descriptionContainer.style.backgroundColor = '#FFD700';  // Background color for description
    descriptionContainer.style.borderRadius = '5px';  // Optional: rounded corners
    descriptionContainer.style.marginTop = '10px';  // Space between gallery and description
    descriptionContainer.style.boxSizing = 'border-box';  // Ensure padding is included in the total height

    // Add description text container for typewriter effect
    var descriptionText = document.createElement('p');
    descriptionText.style.fontSize = '16px';  // Increase font size for better readability
    descriptionText.style.color = '#000';  // Text color
    descriptionText.style.whiteSpace = 'pre-wrap';  // Preserve new lines in text
    descriptionText.style.lineHeight = '1.5';  // Improve readability with better line spacing

    // Append description text to description container
    descriptionContainer.appendChild(descriptionText);

    // Append main image, thumbnails, and description to the gallery container
    galleryContainer.appendChild(mainImage);
    galleryContainer.appendChild(thumbnailContainer);
    galleryContainer.appendChild(descriptionContainer);

    // Append info icon and gallery container to the hotspot
    hotSpotDiv.appendChild(infoIcon);
    hotSpotDiv.appendChild(galleryContainer);

    let isDescriptionTyped = false;  // Flag to track whether the text is already typed

    // Toggle gallery visibility on info icon click
    infoIcon.addEventListener('click', function (event) {
        event.stopPropagation();
        galleryContainer.style.display = galleryContainer.style.display === 'none' ? 'block' : 'none';
        
        if (galleryContainer.style.display === 'block' && !isDescriptionTyped) {
            typeWriterEffect(descriptionText, args.description);  // Trigger typewriter effect when shown
            isDescriptionTyped = true;  // Set flag to true to prevent retyping
        }
    });

    // Hide the gallery when clicking outside
    document.addEventListener('click', function () {
        if (galleryContainer.style.display === 'block') {
            galleryContainer.style.display = 'none';
        }
    });

    // Prevent hiding the gallery when clicking inside the gallery
    galleryContainer.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}

// Function to create a typewriter effect
function typeWriterEffect(element, text, speed = 100) {  // Speed can be adjusted here
    let index = 0;
    element.textContent = "";  // Clear existing text

    function type() {
        if (index < text.length) {
            element.textContent += text.charAt(index);  // Add one character at a time
            index++;
            setTimeout(type, speed);  // Call the function again with a delay for each character
        }
    }

    type();  // Start the typewriter effect
}




        function hotspot(hotSpotDiv, args) {
            hotSpotDiv.classList.add('custom-tooltip');
            var span = document.createElement('span');
            span.innerHTML = args.text;
            hotSpotDiv.appendChild(span);
            span.style.width = span.scrollWidth + 'px';
            span.style.marginLeft = -(span.offsetWidth / 2) + 'px';
            span.style.marginTop = -span.offsetHeight - 12 + 'px';
 
            // Set custom background image
            // hotSpotDiv.style.backgroundImage = 'url(' + args.imageUrl + ')';
            hotSpotDiv.style.backgroundImage = 'url(' + getImagePath('arrow102.png') + ')';
        }

        // Overlay click event
        // document.getElementById('overlay').addEventListener('click', function () {
        //     this.style.display = 'none';
        //     viewer.stopAutoRotate();
        //     viewer.startOrientation();
        // });
        document.addEventListener('DOMContentLoaded', function () {
            const spanElement = document.querySelector('div.pnlm-tooltip span');
            if (spanElement) {
                spanElement.textContent = 'test';
            }
        });

        document.getElementById('pan-up').addEventListener('click', function (e) {
            viewer.setPitch(viewer.getPitch() + 10);
        });
        document.getElementById('pan-down').addEventListener('click', function (e) {
            viewer.setPitch(viewer.getPitch() - 10);
        });
        document.getElementById('pan-left').addEventListener('click', function (e) {
            viewer.setYaw(viewer.getYaw() - 10);
        });
        document.getElementById('pan-right').addEventListener('click', function (e) {
            viewer.setYaw(viewer.getYaw() + 10);
        });
        document.getElementById('zoom-in').addEventListener('click', function (e) {
            viewer.setHfov(viewer.getHfov() - 10);
        });
        document.getElementById('zoom-out').addEventListener('click', function (e) {
            viewer.setHfov(viewer.getHfov() + 10);
        });
        document.getElementById('fullscreen').addEventListener('click', function (e) {
            viewer.toggleFullscreen();
        });
        
        document.getElementById('toggle-hotspot').addEventListener('click', function () {
    // Find all elements with class 'custom-hotspot' (the arrows)
    var hotspots = document.querySelectorAll('.custom-hotspot');

    hotspots.forEach(function(hotspot) {
        if (hotspot.style.display === 'none') {
            hotspot.style.display = 'block'; // Show the hotspot
        } else {
            hotspot.style.display = 'none';  // Hide the hotspot
        }
    });

    // Update the button text to reflect the current state
    if (this.textContent === 'Hide Hotspot') {
        this.textContent = 'Show Hotspot';
    } else {
        this.textContent = 'Hide Hotspot';
    }
});


    </script>

</body>

</html>
