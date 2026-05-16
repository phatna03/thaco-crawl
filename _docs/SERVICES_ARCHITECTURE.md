# Service Architecture

# Services

## SitemapService

Responsibilities:
- detect sitemap
- parse XML
- extract URLs

Methods:
- detectSitemap()
- parseSitemap()
- extractUrls()

---

## LinkDiscoveryService

Fallback discovery when sitemap does not exist.

Methods:
- discoverLinks()

---

## CrawlService

Responsibilities:
- fetch HTML
- process crawling

Methods:
- crawlPage()

---

## ParserService

Responsibilities:
- extract structured content

Methods:
- extractTitle()
- extractContent()
- extractImage()

---

## AIService

Responsibilities:
- summary
- tags
- rewrite

Methods:
- generateSummary()
- generateTags()
- rewriteContent()

---

# Important Rules

- Keep services independent
- Avoid duplicated logic
- Use dependency injection