// Hotspot configuration and utilities
export class Hotspots {
    static TOTAL_HOTSPOTS = 36;
    
    // 36-point capture pattern: 3 rows of 12 points each
    static getHotspots() {
        const hotspots = [];
        const pitchAngles = [45, 0, -45]; // Upper, middle, lower
        const yawStep = 30; // 360° / 12 points
        
        let id = 1;
        for (const pitch of pitchAngles) {
            for (let i = 0; i < 12; i++) {
                const yaw = i * yawStep;
                hotspots.push({
                    id: id++,
                    pitch: pitch,
                    yaw: yaw,
                    captured: false
                });
            }
        }
        
        return hotspots;
    }

    // Get matching graph for spatial relationships
    static getMatchingGraph() {
        return {
            // Upper row (pitch +45°) - IDs 1-12
            '1': ['2', '12', '13', '25'],
            '2': ['1', '3', '14', '26'],
            '3': ['2', '4', '15', '27'],
            '4': ['3', '5', '16', '28'],
            '5': ['4', '6', '17', '29'],
            '6': ['5', '7', '18', '30'],
            '7': ['6', '8', '19', '31'],
            '8': ['7', '9', '20', '32'],
            '9': ['8', '10', '21', '33'],
            '10': ['9', '11', '22', '34'],
            '11': ['10', '12', '23', '35'],
            '12': ['11', '1', '24', '36'],
            // Middle row (equator, pitch 0°) - IDs 13-24 with extra connections for robustness
            '13': ['14', '24', '1', '25', '15', '23'], // Connect to neighbors and neighbors' neighbors
            '14': ['13', '15', '2', '26', '16', '24'],
            '15': ['14', '16', '3', '27', '13', '17'],
            '16': ['15', '17', '4', '28', '14', '18'],
            '17': ['16', '18', '5', '29', '15', '19'],
            '18': ['17', '19', '6', '30', '16', '20'],
            '19': ['18', '20', '7', '31', '17', '21'],
            '20': ['19', '21', '8', '32', '18', '22'],
            '21': ['20', '22', '9', '33', '19', '23'],
            '22': ['21', '23', '10', '34', '20', '24'],
            '23': ['22', '24', '11', '35', '21', '13'],
            '24': ['23', '13', '12', '36', '22', '14'],
            // Lower row (pitch -45°) - IDs 25-36
            '25': ['26', '36', '1', '13'],
            '26': ['25', '27', '2', '14'],
            '27': ['26', '28', '3', '15'],
            '28': ['27', '29', '4', '16'],
            '29': ['28', '30', '5', '17'],
            '30': ['29', '31', '6', '18'],
            '31': ['30', '32', '7', '19'],
            '32': ['31', '33', '8', '20'],
            '33': ['32', '34', '9', '21'],
            '34': ['33', '35', '10', '22'],
            '35': ['34', '36', '11', '23'],
            '36': ['35', '25', '12', '24']
        };
    }

    // Calculate field of view based on pitch angle
    static calculateFOV(pitch) {
        const baseFOV = 67.0;
        const pitchRad = Math.abs(pitch) * Math.PI / 180;
        const fovMultiplier = 1 + (Math.sin(pitchRad) * 0.3); // Up to 30% wider at poles
        return baseFOV * fovMultiplier;
    }

    // Find nearest hotspot to given orientation
    static findNearestHotspot(deviceYaw, devicePitch, hotspots) {
        let minDistance = Infinity;
        let nearestHotspot = null;
        
        for (const hotspot of hotspots) {
            const yawDiff = this.angleDifference(deviceYaw, hotspot.yaw);
            const pitchDiff = Math.abs(devicePitch - hotspot.pitch);
            const distance = Math.sqrt(yawDiff * yawDiff + pitchDiff * pitchDiff);
            
            if (distance < minDistance) {
                minDistance = distance;
                nearestHotspot = hotspot;
            }
        }
        
        return { hotspot: nearestHotspot, distance: minDistance };
    }

    // Calculate angle difference accounting for wrap-around
    static angleDifference(a, b) {
        let diff = Math.abs(a - b);
        if (diff > 180) {
            diff = 360 - diff;
        }
        return diff;
    }
}