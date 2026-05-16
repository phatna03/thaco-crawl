# AI Content Crawler CMS

## Project Goal

Build a Laravel CMS system that can:

- Detect sitemap.xml from any domain
- Extract content URLs automatically
- Crawl content from websites
- Analyze content using AI
- Allow admin users to edit AI-generated results
- Save analyzed content into CMS database
- Manage imported content through responsive admin panel

---

# Main Features

## Authentication
- Admin login
- Protected CMS dashboard

---

## Sitemap Detection

User inputs any domain:

Example:
https://example.com

System will:
1. Check robots.txt
2. Detect sitemap.xml
3. Parse sitemap URLs
4. Show selectable URLs to user

If sitemap does NOT exist:
- Show manual URL input

---

## Content Crawling

System supports:
- Blog article pages
- About pages
- Landing pages
- Generic HTML pages

Extract:
- title
- main content
- image
- metadata

---

## AI Content Analysis

After crawling:
- Generate Vietnamese summary
- Generate related tags
- Rewrite content for new CMS tone

Admin can:
- Edit AI results
- Re-run AI analysis
- Save final version

---

## Content Management

Admin can:
- View imported content list
- Open popup/modal detail preview
- Edit analyzed content
- Delete content

---

# Technical Priorities

1. Clean architecture
2. Fast development
3. Stable workflow
4. Good UX/UI
5. Mobile responsive
6. AI-assisted workflow
7. Maintainable code

---

# Important Notes

- Do NOT overengineer
- Focus on stable demo workflow
- Use service classes
- Use clean UI
- Prioritize reviewer experience