# Kura Digital

White-label campaign website platform for political candidates. Each candidate gets a fully customisable, mobile-first campaign site powered by a shared multi-tenant backend.

## Tech Stack

- **Backend:** Laravel 10 (PHP 8.1+)
- **Frontend:** React 18 + React Router
- **State Management:** Zustand
- **Styling:** Tailwind CSS
- **Database:** MySQL 8
- **Build Tool:** Vite

## Features

- **Multi-tenant / White-label** — each candidate gets a unique slug (`/candidate-slug`)
- **Dynamic theming** — custom colours, logos, and branding per site
- **Bilingual support** — English / Swahili toggle
- **Responsive & mobile-first** — optimised for mobile voters
- **WhatsApp integration** — floating chat button
- **Social media links** — Facebook, Twitter/X, Instagram, TikTok, YouTube
- **Admin-managed content** — manifesto, events, news, gallery, projects
- **Volunteer & contact forms** — collect supporter signups and messages
- **SEO-friendly** — meta tags, clean URLs

## Pages

| Page | Route | Description |
|------|-------|-------------|
| Home | `/:slug` | Hero, pillars, about preview, CTA |
| About | `/:slug/about` | Biography, timeline, milestones |
| Manifesto | `/:slug/manifesto` | Policy pillars with promises |
| Events | `/:slug/events` | Upcoming rallies, town halls |
| News | `/:slug/news` | Campaign updates, press releases |
| Gallery | `/:slug/gallery` | Photo gallery with categories |
| Projects | `/:slug/projects` | Track record & development projects |
| Volunteer | `/:slug/volunteer` | Signup form for volunteers & agents |
| Contact | `/:slug/contact` | Contact form + info |

## Setup

### Prerequisites

- PHP 8.1+
- Composer
- Node.js 18+
- MySQL 8

### Installation

```bash
# Clone the repo
git clone https://github.com/njambijoyk-bit/KuraDigital.git
cd KuraDigital

# Install PHP dependencies
composer install

# Install JS dependencies
npm install --legacy-peer-deps

# Copy environment file
cp .env.example .env
php artisan key:generate

# Configure database in .env
# DB_DATABASE=kura_digital
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# Run migrations and seed demo data
php artisan migrate --seed

# Build frontend assets
npm run build

# Start the dev server
php artisan serve
```

### Development

```bash
# Run Vite dev server (hot reload)
npm run dev

# Run Laravel server
php artisan serve
```

Visit `http://localhost:8000/demo-candidate` to see the demo site.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/sites/{slug}` | Get site by slug |
| GET | `/api/sites/{id}/manifesto` | Get manifesto pillars |
| GET | `/api/sites/{id}/events` | Get upcoming events |
| GET | `/api/sites/{id}/news` | Get news articles |
| GET | `/api/sites/{id}/gallery` | Get gallery items |
| GET | `/api/sites/{id}/projects` | Get projects |
| POST | `/api/sites/{id}/contact` | Submit contact message |
| POST | `/api/sites/{id}/volunteers` | Register volunteer |

## Documentation

- [Technology Decisions](docs/TECH_DECISIONS.md) — architecture, platform choices (Tauri, React Native), and rationale

## License

MIT
