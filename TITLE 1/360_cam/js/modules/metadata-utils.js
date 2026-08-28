// Metadata utilities for adding EXIF and XMP metadata to photospheres

export class MetadataUtils {
    constructor() {
        // Check if piexifjs is available
        if (typeof piexif === 'undefined') {
            console.error('piexif.js not loaded - metadata will be limited');
            this.piexifAvailable = false;
        } else {
            this.piexifAvailable = true;
            console.log('piexif.js loaded successfully');
        }
    }

    // Convert decimal degrees to DMS (Degrees, Minutes, Seconds) for EXIF GPS
    degToDmsRational(degFloat) {
        const minFloat = Math.abs(degFloat) % 1 * 60;
        const secFloat = minFloat % 1 * 60;
        const deg = Math.floor(Math.abs(degFloat));
        const min = Math.floor(minFloat);
        const sec = Math.round(secFloat * 100);
        
        return [[deg, 1], [min, 1], [sec, 100]];
    }

    // Create EXIF metadata with GPS location
    createExifData(options = {}) {
        const exifObj = {
            "0th": {},
            "Exif": {},
            "GPS": {},
            "1st": {}
        };

        // Basic EXIF data
        exifObj["0th"][piexif.ImageIFD.Make] = "VFTCam";
        exifObj["0th"][piexif.ImageIFD.Software] = "VFTCam Photosphere";
        exifObj["0th"][piexif.ImageIFD.DateTime] = this.formatExifDateTime(options.captureDate || new Date());
        
        if (options.width && options.height) {
            exifObj["0th"][piexif.ImageIFD.ImageWidth] = options.width;
            exifObj["0th"][piexif.ImageIFD.ImageLength] = options.height;
        }

        // Extended EXIF data
        exifObj["Exif"][piexif.ExifIFD.DateTimeOriginal] = this.formatExifDateTime(options.firstPhotoDate || options.captureDate || new Date());
        exifObj["Exif"][piexif.ExifIFD.DateTimeDigitized] = this.formatExifDateTime(options.lastPhotoDate || options.captureDate || new Date());
        
        // GPS data if available
        if (options.latitude !== undefined && options.longitude !== undefined) {
            exifObj["GPS"][piexif.GPSIFD.GPSLatitude] = this.degToDmsRational(options.latitude);
            exifObj["GPS"][piexif.GPSIFD.GPSLatitudeRef] = options.latitude < 0 ? 'S' : 'N';
            exifObj["GPS"][piexif.GPSIFD.GPSLongitude] = this.degToDmsRational(options.longitude);
            exifObj["GPS"][piexif.GPSIFD.GPSLongitudeRef] = options.longitude < 0 ? 'W' : 'E';
            
            if (options.altitude !== undefined) {
                exifObj["GPS"][piexif.GPSIFD.GPSAltitude] = [Math.abs(Math.round(options.altitude * 100)), 100];
                exifObj["GPS"][piexif.GPSIFD.GPSAltitudeRef] = options.altitude < 0 ? 1 : 0;
            }
            
            // Add GPS timestamp
            const gpsDate = options.captureDate || new Date();
            exifObj["GPS"][piexif.GPSIFD.GPSDateStamp] = this.formatGPSDate(gpsDate);
            exifObj["GPS"][piexif.GPSIFD.GPSTimeStamp] = this.formatGPSTime(gpsDate);
        }

        return exifObj;
    }

    // Format date for EXIF
    formatExifDateTime(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');
        return `${year}:${month}:${day} ${hours}:${minutes}:${seconds}`;
    }

    // Format date for GPS
    formatGPSDate(date) {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}:${month}:${day}`;
    }

    // Format time for GPS
    formatGPSTime(date) {
        const d = new Date(date);
        return [
            [d.getUTCHours(), 1],
            [d.getUTCMinutes(), 1],
            [d.getUTCSeconds(), 1]
        ];
    }

    // Create XMP packet with GPano metadata
    createXMPPacket(options = {}) {
        const width = options.width || 4096;
        const height = options.height || 2048;
        
        // Use compass-aligned headings when available for real-world alignment
        // PoseHeadingDegrees: The compass heading when the first photo was taken
        // InitialViewHeadingDegrees: The direction the viewer should initially face
        const poseHeading = options.poseHeading !== undefined ? 
            Math.round((options.poseHeading + 360) % 360 * 10) / 10 : 0;
        const initialHeading = options.initialHeading !== undefined ?
            Math.round((options.initialHeading + 360) % 360 * 10) / 10 : poseHeading;
        
        const xmpData = `<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="VFTCam">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about=""
        xmlns:GPano="http://ns.google.com/photos/1.0/panorama/"
        xmlns:xmp="http://ns.adobe.com/xap/1.0/"
        xmlns:dc="http://purl.org/dc/elements/1.1/"
        xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/">
      <GPano:UsePanoramaViewer>True</GPano:UsePanoramaViewer>
      <GPano:CaptureSoftware>VFTCam</GPano:CaptureSoftware>
      <GPano:StitchingSoftware>VFTCam WebGL2</GPano:StitchingSoftware>
      <GPano:ProjectionType>equirectangular</GPano:ProjectionType>
      <GPano:PoseHeadingDegrees>${poseHeading}</GPano:PoseHeadingDegrees>
      <GPano:InitialViewHeadingDegrees>${initialHeading}</GPano:InitialViewHeadingDegrees>
      <GPano:InitialViewPitchDegrees>${options.initialPitch || 0}</GPano:InitialViewPitchDegrees>
      <GPano:InitialViewRollDegrees>0</GPano:InitialViewRollDegrees>
      <GPano:InitialHorizontalFOVDegrees>75</GPano:InitialHorizontalFOVDegrees>
      <GPano:CroppedAreaImageWidthPixels>${width}</GPano:CroppedAreaImageWidthPixels>
      <GPano:CroppedAreaImageHeightPixels>${height}</GPano:CroppedAreaImageHeightPixels>
      <GPano:FullPanoWidthPixels>${width}</GPano:FullPanoWidthPixels>
      <GPano:FullPanoHeightPixels>${height}</GPano:FullPanoHeightPixels>
      <GPano:CroppedAreaLeftPixels>0</GPano:CroppedAreaLeftPixels>
      <GPano:CroppedAreaTopPixels>0</GPano:CroppedAreaTopPixels>
      <GPano:FirstPhotoDate>${options.firstPhotoDate || new Date().toISOString()}</GPano:FirstPhotoDate>
      <GPano:LastPhotoDate>${options.lastPhotoDate || new Date().toISOString()}</GPano:LastPhotoDate>
      <GPano:SourcePhotosCount>${options.sourcePhotosCount || 36}</GPano:SourcePhotosCount>
      <GPano:ExposureLockUsed>False</GPano:ExposureLockUsed>
      <xmp:CreatorTool>VFTCam</xmp:CreatorTool>
      <xmp:CreateDate>${options.captureDate || new Date().toISOString()}</xmp:CreateDate>
      <dc:description>360 degree photosphere created with VFTCam</dc:description>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>`;
        
        return xmpData;
    }

    // Convert string to byte array
    stringToBytes(str) {
        const bytes = [];
        for (let i = 0; i < str.length; i++) {
            bytes.push(str.charCodeAt(i));
        }
        return bytes;
    }

    // Inject XMP metadata into JPEG data
    injectXMPIntoJPEG(jpegDataUrl, xmpPacket) {
        // Convert data URL to binary
        const base64 = jpegDataUrl.split(',')[1];
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        // Find position after SOI marker (0xFFD8)
        let insertPos = 2; // After SOI
        
        // Skip any existing APP0 segment (JFIF)
        if (bytes[2] === 0xFF && bytes[3] === 0xE0) {
            const segmentLength = (bytes[4] << 8) | bytes[5];
            insertPos = 2 + 2 + segmentLength;
        }

        // Create XMP APP1 segment
        const xmpIdentifier = "http://ns.adobe.com/xap/1.0/\0";
        const xmpBytes = this.stringToBytes(xmpPacket);
        const identifierBytes = this.stringToBytes(xmpIdentifier);
        
        // APP1 marker (0xFFE1) + length (2 bytes) + identifier + XMP data
        const segmentLength = 2 + identifierBytes.length + xmpBytes.length;
        const app1Segment = new Uint8Array(2 + segmentLength);
        
        // APP1 marker
        app1Segment[0] = 0xFF;
        app1Segment[1] = 0xE1;
        
        // Segment length (big-endian)
        app1Segment[2] = (segmentLength >> 8) & 0xFF;
        app1Segment[3] = segmentLength & 0xFF;
        
        // XMP identifier
        let offset = 4;
        for (let i = 0; i < identifierBytes.length; i++) {
            app1Segment[offset++] = identifierBytes[i];
        }
        
        // XMP packet
        for (let i = 0; i < xmpBytes.length; i++) {
            app1Segment[offset++] = xmpBytes[i];
        }

        // Combine: start + APP1 segment + rest of JPEG
        const result = new Uint8Array(bytes.length + app1Segment.length);
        
        // Copy start of JPEG
        for (let i = 0; i < insertPos; i++) {
            result[i] = bytes[i];
        }
        
        // Insert APP1 segment
        for (let i = 0; i < app1Segment.length; i++) {
            result[insertPos + i] = app1Segment[i];
        }
        
        // Copy rest of JPEG
        for (let i = insertPos; i < bytes.length; i++) {
            result[insertPos + app1Segment.length + i - insertPos] = bytes[i];
        }

        return result;
    }

    // Add all metadata to a JPEG image
    async addMetadataToJPEG(jpegDataUrl, metadata = {}) {
        try {
            let jpegWithMetadata = jpegDataUrl;
            
            // Step 1: Add EXIF metadata using piexifjs if available
            if (this.piexifAvailable) {
                const exifObj = this.createExifData(metadata);
                const exifBytes = piexif.dump(exifObj);
                jpegWithMetadata = piexif.insert(exifBytes, jpegDataUrl);
            } else {
                console.warn('Skipping EXIF metadata - piexif not available');
            }
            
            // Step 2: Create and inject XMP metadata
            const xmpPacket = this.createXMPPacket(metadata);
            const jpegBytes = this.injectXMPIntoJPEG(jpegWithMetadata, xmpPacket);
            
            // Convert to blob
            const blob = new Blob([jpegBytes], { type: 'image/jpeg' });
            
            return blob;
        } catch (error) {
            console.error('Error adding metadata to JPEG:', error);
            // Fallback: return original as blob
            const response = await fetch(jpegDataUrl);
            return await response.blob();
        }
    }

    // Convert data URL to blob with metadata
    async dataURLToBlob(dataUrl, metadata = {}) {
        // If it's already a JPEG, add metadata
        if (dataUrl.startsWith('data:image/jpeg')) {
            return await this.addMetadataToJPEG(dataUrl, metadata);
        }
        
        // Otherwise just convert to blob
        const response = await fetch(dataUrl);
        return await response.blob();
    }
}

// Export singleton instance
export const metadataUtils = new MetadataUtils();