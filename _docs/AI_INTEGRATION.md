# AI Integration

# AI Provider

OpenAI API

---

# AI Features

## 1. Summary Generation

Goal:
Generate concise Vietnamese summary.

---

## 2. Auto Tagging

Goal:
Generate related keywords.

Output:
JSON array

Example:
[
  "THACO",
  "Automotive",
  "Manufacturing"
]

---

## 3. Content Rewrite

Goal:
Rewrite content for:
- cleaner CMS style
- better readability
- professional tone

---

# AI Service Structure

App\Services\AIService

Methods:
- generateSummary()
- generateTags()
- rewriteContent()

---

# Retry Workflow

Admin can click:
"Phân tích lại"

System will:
- call AI again
- replace old AI results

---

# Important Rules

- AI logic isolated in service
- Handle API failures
- Preserve original content
- Avoid excessive token usage