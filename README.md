# AI-Powered AR 360° Virtual Campus Navigation System

**Branch:** `main`  
**Repository:** Innovatech PH Campus Navigation Platform

An educational technology platform that combines **augmented reality (AR)**, **360° immersive imaging**, and **artificial intelligence** to enhance how students, prospective applicants, and visitors experience educational campuses. This system brings physical spaces into the digital realm with interactive and intelligent features.

## Video Preview

[Watch the system in action](preview.mp4)

## Technology Overview

This system harnesses emerging technologies to create immersive educational experiences:

- **360° Panoramic Imaging**: Full-sphere capture and rendering of campus spaces
- **Augmented Reality (AR)**: Real-time digital overlays on physical campus views
- **AI-Powered Content Generation**: Automated intelligent descriptions and content creation
- **Interactive Floor Plans**: Dynamic, responsive campus mapping with smart navigation
- **Computer Vision**: Visual target recognition for location-aware experiences
- **Immersive Web Technologies**: A-Frame-based VR/AR experiences in the browser

## Credits

**Lead Developer & Architect:**  
**Sean Charles Puigosa**  
*Full-stack development, system architecture, and implementation*

This system is a thesis project demonstrating AR, 360° imaging, and AI integration in educational technology applications.

## Technological Capabilities

### 360° Immersive Imaging Technology
- **Full-Sphere Panoramic Capture**: Complete 360° horizontal and vertical coverage
- **A-Frame Integration**: WebVR framework for browser-based immersive experiences
- **Hotspot Navigation**: Interactive points of interest within panoramic scenes
- **Room-to-Room Transitions**: Seamless movement between connected spaces
- **Mobile-Optimized Rendering**: Landscape full-screen mode for immersive mobile experiences
- **Equirectangular Projection**: Standard panorama format for broad compatibility

### Augmented Reality (AR) Implementation
- **Live AR Walking Mode**: Real-time camera overlay with digital information
- **Computer Vision Detection**: Visual target recognition for location awareness
- **GPS Integration**: Precise location tracking and waypoint matching
- **Digital Information Overlays**: Real-time labels, directions, and interactive content
- **Marker-Based AR**: Image recognition for triggering location-specific content
- **Google Maps Live View Style**: Familiar AR navigation interface
- **Waypoint System**: GPS and visual target-based location detection

### Artificial Intelligence Integration
- **AI Panorama Stitching**: Automatic merging of multiple images into seamless 360° panoramas
- **Intelligent Content Generation**: AI-powered descriptions for buildings, rooms, and facilities
- **Smart Capture Processing**: AI enhancement of in-app captured images
- **Natural Language Processing**: Context-aware description generation
- **Job Queue System**: Asynchronous AI processing with status tracking
- **Ollama Integration**: Local AI processing for privacy and control

### Interactive Digital Mapping
- **Responsive Floor Plans**: Dynamic campus mapping that adapts to any screen size
- **Percentage-Based Coordinate System**: Device-independent marker positioning
- **Drag-and-Drop Interface**: Visual marker placement without coding
- **Multi-Floor Support**: Seamless navigation between building levels
- **Click-to-Navigate**: Direct floor plan to 360° scene integration
- **Aspect Ratio Preservation**: Consistent mapping across all devices

### Web-Based Immersive Experience
- **Browser-Based VR/AR**: No app installation required
- **Cross-Platform Compatibility**: Works on desktop, tablet, and mobile
- **Progressive Web App (PWA)**: Offline capabilities and app-like experience
- **Real-Time Rendering**: Smooth 60fps immersive experiences
- **Touch-Optimized Controls**: Intuitive gesture-based navigation
- **Responsive Design**: Adaptive UI for all screen sizes

## Technology Stack

- **Backend**: PHP 8.3+ (Object-oriented, MVC architecture)
- **Database**: MySQL 8.0+ (Schema design for campus data)
- **Frontend**: JavaScript (ES6+), A-Frame for 360°/AR
- **CSS**: Custom responsive design with CSS Grid/Flexbox
- **AI Services**: Ollama integration for local AI processing
- **File Management**: Drag-and-drop media uploader
- **Authentication**: Session-based auth with role management

## Project Structure

```
G7-4D-THESIS/
├── admin/                    # AR/360 content management dashboard
│   ├── owner/               # Platform administration
│   ├── institution/         # Institution AR/360 management
│   ├── staff/               # Content creation & AI tools
│   └── layout/              # Shared admin components
├── api/                     # AR/360 API endpoints
├── assets/                  # Frontend assets (CSS, JS, images)
├── database/                # SQL schema and migrations
├── docs/                    # Documentation and setup guides
├── includes/                # Core PHP libraries
│   ├── config.php          # Database and system config
│   ├── auth.php            # Authentication system
│   ├── ollama-wrapper.php   # AI integration
│   └── functions.php       # Utility functions
├── organizations/           # Per-institution AR/360 content
│   └── {institution_name}/ # 360 scenes, AR targets, assets
├── public/                  # Public-facing assets
├── pwa/                     # Progressive Web App files
├── scripts/                 # Utility and setup scripts
├── storage/                 # File storage (uploads, cache)
├── templates/               # Institution templates
├── index.php               # Main landing page
├── router.php              # URL routing
├── sw.js                   # Service worker for PWA
└── .env                    # Environment configuration
```

## Database Schema

The system uses a MySQL schema designed for AR/360 content management:

- **users**: User accounts with role-based permissions
- **institutions**: Institution management for campus deployment
- **buildings**, **rooms**, **campus_areas**, **facilities**: Physical space hierarchy
- **tour_scenes**: 360° scene management with navigation hotspots
- **floor_plans**, **floor_plan_markers**: Interactive digital mapping system
- **ar_targets**, **ar_waypoints**: AR detection and positioning data
- **ar_walk_sessions**, **ar_walk_pings**: AR walking analytics
- **ai_stitch_jobs**, **ai_info_jobs**: AI processing queue for panorama stitching and content generation
- **audit_logs**: System activity tracking

**Canonical schema**: `database/schema.sql`

## Installation & Setup

### Prerequisites
- PHP 8.3 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server with mod_rewrite
- Ollama (for AI features - panorama stitching and content generation)
- Modern web browser with WebGL support

### Step 1: Clone the Repository
```bash
git clone <repository-url>
cd G7-4D-THESIS
```

### Step 2: Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your database credentials:
```env
DB_HOST=localhost
DB_NAME=innovatech_campus
DB_USER=your_username
DB_PASS=your_password
BASE_URL=http://localhost/G7-4D-THESIS
```

### Step 3: Import Database Schema
```bash
mysql -u your_username -p innovatech_campus < database/schema.sql
```

### Step 4: Set Directory Permissions
```bash
chmod -R 755 storage/
chmod -R 755 organizations/
```

### Step 5: Configure AI Services (Optional)
Install and configure Ollama for AI features:
```bash
# Install Ollama (follow official guide)
# Start Ollama service
ollama serve

# Pull preferred model
ollama pull llama2
```

### Step 6: Configure Web Server
Ensure Apache mod_rewrite is enabled for URL routing and proper handling of A-Frame assets.

### Step 7: Access the System
- **Landing Page**: `http://localhost/G7-4D-THESIS/`
- **Admin Dashboard**: `http://localhost/G7-4D-THESIS/admin/`

Default admin account will be created during first setup.

## Usage Guide

### Creating 360° Virtual Tours
1. Access the admin dashboard and navigate to Tour Studio
2. Upload 360° panoramic images or use in-app capture with AI stitching
3. Define navigation hotspots between scenes
4. Add descriptive information (AI-assisted generation available)
5. Set starting scene and tour paths
6. Preview and publish the immersive experience

### Building Interactive Floor Plans
1. Upload campus floor plan images to the system
2. Use drag-and-drop interface to place location markers
3. Configure marker positions using percentage-based coordinates
4. Link markers to specific 360° scenes or information
5. Set up multi-floor navigation with smooth transitions
6. Test responsive behavior across different screen sizes

### Implementing AR Experiences
1. Configure AR targets using computer vision or GPS waypoints
2. Set up detection radius and visual markers
3. Define overlay content (labels, directions, information)
4. Test AR walking mode with mobile devices
5. Calibrate GPS and visual target detection
6. Deploy AR experience for campus visitors

### Using AI-Powered Tools
1. **AI Stitch**: Upload cubemap faces or use in-app guided capture
2. **AI Content Generation**: Select buildings/rooms/facilities for auto-description
3. Monitor AI job queue for processing status
4. Review and edit AI-generated content
5. Use AI enhancement for captured panoramas

### For End Users
1. Visit institution landing page
2. Choose between 360° rotation or floor plan landing modes
3. Explore immersive 360° virtual tours with hotspots
4. Navigate using interactive floor plans
5. Access AR walking mode on mobile devices
6. Experience real-time AR information overlays

## Experience Modes

### 1. 360° Immersive Mode
- Full-screen A-Frame 360° panoramic viewer
- Starts at configured starting scene
- Users can rotate and navigate between connected scenes
- Ideal for immersive first impressions and virtual exploration

### 2. Interactive Floor Plan Mode
- Campus floor plan as the primary landing interface
- Drag-and-drop circular markers for key locations
- Click markers to enter 360° tours or view information
- Better for spatial orientation and wayfinding

## Configuration

### AI Services Integration
Configure Ollama for local AI processing in `includes/ollama-wrapper.php`:
```php
$ollamaUrl = 'http://localhost:11434/api/generate';
$model = 'llama2'; // or your preferred model
```

### 360° Scene Configuration
- Set panorama resolution (recommended 4096x2048)
- Configure hotspot detection radius
- Define navigation transitions between scenes
- Set default camera orientation

### AR Configuration
- Configure GPS accuracy thresholds
- Set visual target detection sensitivity
- Define AR overlay content templates
- Configure waypoint detection radius

### Floor Plan Coordinate System
All markers use percentage-based coordinates (0-1 range) for responsive scaling:
```javascript
{
  x: 0.5,  // 50% from left
  y: 0.3   // 30% from top
}
```

## Security Features

- Role-based access control (RBAC) for content management
- Session-based authentication with secure handling
- SQL injection prevention (prepared statements)
- XSS protection (output escaping)
- CSRF token validation
- File upload validation and sanitization
- Audit logging for all administrative actions

## Mobile & AR Optimization

- Landscape full-screen mode for optimal 360°/AR viewing
- Touch-optimized controls for immersive navigation
- Responsive floor plan scaling across all devices
- Camera access integration for AR experiences
- Progressive Web App (PWA) support for offline capabilities
- GPS integration for location-aware AR features

## Troubleshooting

### 360° Rendering Issues
- Check WebGL support in browser
- Verify panorama image format (equirectangular)
- Ensure sufficient GPU resources
- Check A-Frame console for errors

### AR Detection Problems
- Ensure camera permissions are granted
- Check GPS/location services are enabled
- Verify AR targets are properly configured
- Test in well-lit environments for better computer vision

### AI Processing Failures
- Verify Ollama is running: `ollama serve`
- Check API endpoint configuration
- Review AI job queue in database
- Ensure sufficient system resources for AI processing

### Floor Plan Responsiveness
- Verify percentage-based coordinates
- Check image aspect ratio preservation
- Test across different screen sizes
- Ensure CSS `object-fit: contain` is applied

### Performance Issues
- Optimize panorama image sizes
- Enable browser caching
- Check server response times
- Consider CDN for static assets

## Contributing

This is an open-source project for AR and 360° technologies in education. Contributions are welcome:

1. Fork the repository
2. Create a feature branch
3. Make your changes following existing patterns
4. Test across different devices and browsers
5. Submit a pull request with description

## License

This project is open-source. Please refer to the LICENSE file for details.

## Support

For issues, questions, or contributions:
- Open an issue on GitHub
- Contact: [support email]
- Documentation: Check the `docs/` directory for detailed guides

## Technology Acknowledgments

- **A-Frame**: WebVR framework for browser-based 360° and AR experiences
- **Ollama**: Local AI processing for privacy-focused content generation
- **Three.js**: 3D rendering engine powering immersive web experiences
- **WebGL**: Hardware-accelerated graphics for smooth 360° rendering
- **Progressive Web App**: Technologies for offline capabilities and app-like experiences
- **Open Source Community**: Inspiration and tools that made this project possible

---

**Developed by Sean Charles Puigosa**  
*Thesis Project - AI-Powered AR 360° Virtual Campus Navigation System*  
*Educational Technology Innovation*

**Status**: Production Ready | Open Source | AR/360 Platform

---

> "This system combines AR, 360° imaging, and AI to create immersive campus experiences that connect physical and digital learning environments."