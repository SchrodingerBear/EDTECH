import {
  Navigation,
  Building2,
  Siren,
  Brain,
  MapPinned,
  ScanEye,
  Route,
  Languages,
  Layers,
  BatteryCharging,
  Gauge,
  Workflow,
  Map,
  ShieldCheck,
  Bell,
  Receipt,
  Users,
  Ticket,
  Cpu,
  Cloud,
  Database,
  Boxes,
  GraduationCap,
  Building,
} from "lucide-react";

export const meta = {
  institution: "Immaculada Concepcion College",
  college: "College of Computer Studies",
  center: "Center for Research, Innovation, and Development (CRID)",
  academicYear: "AY 2025–2026",
};

export const proponents = [
  {
    name: "Sean Charles Vicente Pugosa",
    role: "Principal Investigator",
    initials: "SP",
  },
  {
    name: "Cedric Fuerzas Sy",
    role: "Co-Investigator",
    initials: "CS",
  },
  {
    name: "Christian Abuyabor",
    role: "Co-Investigator",
    initials: "CA",
  },
];

export const panel = [
  {
    name: "Mr. Eser Fernandez",
    role: "Research Adviser",
    initials: "EF",
  },
  {
    name: "Mr. Jonathan E. Pariente, MIT",
    role: "Program Head / Chairman",
    initials: "JP",
  },
];

export const systems = [
  {
    id: "ar-navigation",
    index: "01",
    icon: Navigation,
    accent: "brand",
    shortTitle: "AR Campus Navigation",
    officialTitle:
      "AI-Assisted AR 360° Virtual Campus Navigation and Information System for Educational Institutions",
    client: "Innovatech PH",
    clientNote: "Software Development / Information Technology Department",
    framework:
      "Web-Based Software-as-a-Service (SaaS) Platform using Three-Tier Architecture (Client-Side, Application Server, Database Server)",
    locale: "Innovatech PH",
    summary:
      "A multi-tenant SaaS platform that transforms standardized smartphone-captured panoramas into interactive 360° augmented-reality campus navigation experiences, enabling educational institutions to deploy independent branded virtual tours.",
    domains: [
      {
        icon: ScanEye,
        title: "AI-Integrated Panoramic Processing & Cubemap Stitching",
        desc: "Algorithmic conversion of standardized smartphone-captured flat projections into mathematical 360° cubic spherical environments.",
      },
      {
        icon: MapPinned,
        title: "Semantic Interactive Spatial Hotspots",
        desc: "Dynamic metadata binding on visual targets to execute database queries rendering room specifications, occupancy, and physical descriptors.",
      },
      {
        icon: Layers,
        title: "Markerless Augmented Reality Overlay Protocol",
        desc: "Real-time spatial tracking utilizing web-browser cameras to project digital navigation labels and geographic overlays over the live physical environment.",
      },
      {
        icon: Route,
        title: "Topological 2D Floorplan Mapping & Point-to-Point Routing",
        desc: "A dynamic cartographic database calculating pathfinding vectors and projecting active 'You Are Here' orientation nodes synchronized with the user's spatial perspective.",
      },
      {
        icon: Languages,
        title: "Natural Language Processing Facility Description Generator",
        desc: "Administrative AI subsystem using OpenAI API to synthesize spatial parameters and automate semantic summary generation for campus facilities.",
      },
      {
        icon: Boxes,
        title: "Multi-School Configuration Interface (Multi-Tenant SaaS Engine)",
        desc: "Multi-tenant database architecture partitioning school data to allow separate institutions to deploy independent branded instances.",
      },
    ],
  },
  {
    id: "kd-building",
    index: "02",
    icon: Building2,
    accent: "emerald",
    shortTitle: "KD Building Property Management",
    officialTitle:
      "AI-Integrated Multi-Building Property and Tenant Management System for KDBuilding",
    client: "KDBuilding",
    clientNote: "KD Residence, Compostela, Cebu",
    framework: "Centralized Web-Based Enterprise Resource Planning (ERP) System",
    locale: "KDBuilding — Compostela, Cebu",
    summary:
      "A centralized ERP system unifying property administration, automated utility billing, tenant complaint lifecycle management, and AI-driven maintenance forecasting for multi-building residential operations.",
    domains: [
      {
        icon: Building,
        title: "Centralized Multi-Property Real-Time Administrative Ledger",
        desc: "Spatial database managing buildings, unit layouts, occupancy logs, and owner-tenant relational mapping.",
      },
      {
        icon: Receipt,
        title: "Automated Revenue Reconciliation & Utility Billing",
        desc: "Financial engine automating utility logging (power and water), calculating dynamic billing, generating ledgers, and tracking digital collections.",
      },
      {
        icon: Ticket,
        title: "Tenant Complaint Lifecycle Management & Maintenance Ticketing",
        desc: "Automated ticketing pipeline prioritizing, assigning, and routing structural complaints to maintenance teams, while computing resource costs.",
      },
      {
        icon: ShieldCheck,
        title: "Visitor Access Control & Security Logistics Log",
        desc: "Security module maintaining real-time databases of physical entrants, shift schedules of security personnel, and active facility logs.",
      },
      {
        icon: Brain,
        title: "AI Analytical Engine & Notification Delivery Subsystem",
        desc: "Machine learning forecasting to predict maintenance needs based on historical ticket frequencies, paired with SMS/Email protocols for automated bill and complaint reminders.",
      },
    ],
  },
  {
    id: "lgu-emergency",
    index: "03",
    icon: Siren,
    accent: "rose",
    shortTitle: "LGU Emergency Response",
    officialTitle:
      "AI-Assisted Emergency Response and Disaster Risk Navigation System for Local Government Units",
    client: "Innovatech PH",
    clientNote: "Reusable product deployed for Local Government Units",
    framework:
      "IoT-Enabled Cloud Platform utilizing Real-Time Protocols (MQTT/WebSockets) and Predictive Modeling Subsystems",
    locale: "Innovatech PH (for LGUs)",
    summary:
      "A reusable cloud platform equipping Local Government Units with AI-assisted disaster risk navigation, real-time emergency response coordination, and predictive hazard modeling.",
    domains: [
      {
        icon: Siren,
        title: "AI-Assisted Emergency Response Coordination",
        desc: "Real-time dispatch and resource-allocation engine routing emergency incidents to the nearest response units with predictive priority scoring.",
      },
      {
        icon: Map,
        title: "Disaster Risk Navigation & Hazard Mapping",
        desc: "Dynamic geospatial mapping of flood, fire, and seismic risk zones with evacuation routing integrated into live incident overlays.",
      },
      {
        icon: Gauge,
        title: "Predictive Hazard Modeling Subsystem",
        desc: "Machine learning regression models analyzing historical disaster data, weather telemetry, and sensor feeds to forecast risk severity and response demand.",
      },
      {
        icon: Workflow,
        title: "Inter-Agency Coordination & Incident Lifecycle Pipeline",
        desc: "Centralized incident workflow routing alerts across LGU departments, response teams, and auxiliary agencies with audit-grade logging.",
      },
      {
        icon: Bell,
        title: "Automated Public Alert & Notification Delivery",
        desc: "Multi-channel SMS, email, and push notification subsystem triggering automated risk advisories and evacuation directives to registered constituents.",
      },
      {
        icon: Users,
        title: "Constituent Registry & Shelter Management",
        desc: "Population database tracking resident vulnerability profiles, shelter capacity, and evacuation compliance during active disaster events.",
      },
    ],
  },
];

export const framework = {
  theory: {
    name: "Technology Acceptance Model (TAM)",
    citation: "Davis, 1989",
    desc: "Evaluates Perceived Usefulness (PU) and Perceived Ease of Use (PEOU) among system administrators and end-users to validate adoption viability.",
  },
  instrument: {
    name: "ISO/IEC 25010 Software Quality Standard",
    desc: "Structured questionnaire evaluating Usability, Efficiency, and Security across each developed system.",
  },
  stack: [
    { icon: Cpu, label: "AI / Machine Learning", value: "OpenAI API · Regression Models" },
    { icon: Cloud, label: "Cloud & Real-Time", value: "MQTT · WebSockets · SaaS" },
    { icon: Database, label: "Data Layer", value: "Multi-Tenant PostgreSQL" },
    { icon: ScanEye, label: "Spatial & AR", value: "WebXR · Cubemap Stitching" },
    { icon: Layers, label: "Architecture", value: "Three-Tier · ERP · IoT" },
  ],
};

export const nav = [
  { label: "Overview", href: "#overview" },
  { label: "Systems", href: "#systems" },
  { label: "Framework", href: "#framework" },
  { label: "Proponents", href: "#proponents" },
];


