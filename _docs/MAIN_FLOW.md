# Main Workflow

# Scenario 1 — Sitemap Exists

Admin inputs domain

↓

System checks:
- robots.txt
- sitemap.xml
- sitemap_index.xml

↓

System parses sitemap

↓

Show selectable URL list

↓

Admin selects URL

↓

System crawls content

↓

AI analyzes content

↓

Show editable AI results

↓

Admin edits content if needed

↓

Save database

---

# Scenario 2 — No Sitemap

Admin inputs domain

↓

System cannot find sitemap

↓

Show manual URL input

↓

Admin pastes any URL

↓

System crawls page content

↓

AI analyzes content

↓

Show editable AI results

↓

Save database

---

# AI Workflow

After crawling:
- generate summary
- generate tags
- rewrite content

Admin can:
- edit manually
- re-run AI analysis

---

# Listing Workflow

Admin can:
- view imported content list
- search content
- filter content
- open popup preview
- edit content
- delete content

---

# Error Handling

If crawling fails:
- show notification
- log error
- do not save invalid data

If AI fails:
- allow manual retry
- preserve original content