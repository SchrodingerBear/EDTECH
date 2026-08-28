<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>A-Frame 360 VR Tour</title>
    <script src="https://aframe.io/releases/1.2.0/aframe.min.js"></script>
    <script src="https://unpkg.com/aframe-look-at-component@0.5.1/dist/aframe-look-at-component.min.js"></script>

    <!-- Register hotspot components -->
    <script>
        AFRAME.registerComponent('hotspots', {
            init: function () {
                var currentGroup = 'group-point1'; // Start with the initial visible group

                this.el.addEventListener('reloadspots', function (evt) {
                    var currspotgroup = document.getElementById(evt.detail.currspots);
                    currspotgroup.setAttribute("scale", "0 0 0");

                    var newspotgroup = document.getElementById(evt.detail.newspots);
                    newspotgroup.setAttribute("scale", "1 1 1");

                    currentGroup = evt.detail.newspots; // Update currentGroup
                });

                // Initially hide all groups except the starting group
                this.el.querySelectorAll('a-entity[id^="group-"]').forEach(function (group) {
                    if (group.id !== currentGroup) {
                        group.setAttribute("scale", "0 0 0");
                    }
                });
            }
        });

        AFRAME.registerComponent('spot', {
            schema: {
                linkto: { type: "string", default: "" },
                spotgroup: { type: "string", default: "" },
                rotation: { type: "string", default: "0 0 0" } // Added rotation attribute
            },
            init: function () {
                var data = this.data;
                this.el.setAttribute("src", "#hotspot");
                this.el.setAttribute("look-at", "#cam");

                this.el.addEventListener('click', function () {
                    var sky = document.getElementById("skybox");
                    sky.setAttribute("src", data.linkto);
                    sky.setAttribute("rotation", data.rotation); // Update rotation

                    var spotcomp = document.getElementById("spots");
                    var currspots = this.parentElement.getAttribute("id");

                    spotcomp.emit('reloadspots', { newspots: data.spotgroup, currspots: currspots });

                    console.log('Clicked! New Skybox:', data.linkto, 'Current Spot Group:', currspots);
                });
            }
        });
    </script>
</head>

<body>
    <a-scene device-orientation-permission-ui="enabled: false" keyboard-shortcuts inspector vr-mode-ui screenshot>
        <!-- Skybox: Define the starting image of the tour -->
        <a-sky rotation="0 100 0" id="skybox" src="#point1"></a-sky>

        <!-- Define 360 images -->
        <img id="point1" src="assets/img/360/large/1.jpg" />
        <img id="point2" src="assets/img/360/large/2.jpg" />
        <img id="point3" src="assets/img/360/large/3.jpg" />
        <img id="point4" src="assets/img/360/large/4.jpg" />
        <img id="point5" src="assets/img/360/large/5.jpg" />
        <img id="point6" src="assets/img/360/large/6.jpg" />
        <img id="point7" src="assets/img/360/large/7.jpg" />
        <img id="point8" src="assets/img/360/large/8.jpg" />
        <img id="point9" src="assets/img/360/large/9.jpg" />

        <img id="hotspot" src="assets/hotspot.gif" />


        <!-- Hotspot component -->
        <a-entity id="spots" hotspots>
            <!-- Point 1 has one hotspot -->
            <a-entity id="group-point1">
                <a-image spot="linkto:#point2;spotgroup:group-point2" position="-0.5 -1 -10" src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 2 has two hotspots -->
            <a-entity id="group-point2">
                <a-image spot="linkto:#point1;spotgroup:group-point1" position="0 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point3;spotgroup:group-point3" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 3 has three hotspots -->
            <a-entity id="group-point3">
                <a-image spot="linkto:#point2;spotgroup:group-point2" position="-1 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point4;spotgroup:group-point4" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point5;spotgroup:group-point5" position="0 -1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 4 has two hotspots -->
            <a-entity id="group-point4">
                <a-image spot="linkto:#point3;spotgroup:group-point3" position="-0.5 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point6;spotgroup:group-point6" position="0.5 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 5 has two hotspots -->
            <a-entity id="group-point5">
                <a-image spot="linkto:#point4;spotgroup:group-point4" position="-1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point7;spotgroup:group-point7" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 6 has three hotspots -->
            <a-entity id="group-point6">
                <a-image spot="linkto:#point5;spotgroup:group-point5" position="-1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point8;spotgroup:group-point8" position="0 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point9;spotgroup:group-point9" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 7 has two hotspots -->
            <a-entity id="group-point7">
                <a-image spot="linkto:#point6;spotgroup:group-point6" position="-1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point8;spotgroup:group-point8" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 8 has three hotspots -->
            <a-entity id="group-point8">
                <a-image spot="linkto:#point7;spotgroup:group-point7" position="-1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point6;spotgroup:group-point6" position="0 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point9;spotgroup:group-point9" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>

            <!-- Point 9 has four hotspots -->
            <a-entity id="group-point9">
                <a-image spot="linkto:#point8;spotgroup:group-point8" position="-1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point7;spotgroup:group-point7" position="0 1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point3;spotgroup:group-point3" position="1 0 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
                <a-image spot="linkto:#point4;spotgroup:group-point4" position="0 -1 -5" rotation="0 0 0"
                    src="#hotspot"></a-image>
            </a-entity>
        </a-entity>

        <!-- Camera and cursor -->
        <a-entity id="cam" camera look-controls="pointerLockEnabled: true">
            <a-entity cursor="fuse:true;fuseTimeout:2000" geometry="primitive:ring;radiusInner:0.01;radiusOuter:0.02"
                position="0 0 -1.8" material="shader:flat;color:#ff0000"
                animation__mouseenter="property:scale;to:3 3 3;startEvents:mouseenter;endEvents:mouseleave;dir:reverse;dur:2000;loop:1">
            </a-entity>
        </a-entity>

    </a-scene>
</body>

</html>