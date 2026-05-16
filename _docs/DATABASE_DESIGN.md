# Database Design

# posts

| Column | Type |
|---|---|
| id | bigint |
| title | string |
| slug | string |
| source_url | text |
| original_content | longText |
| ai_summary | text |
| ai_tags | json |
| ai_rewritten_content | longText |
| thumbnail | string |
| metadata | json |
| created_at | timestamp |
| updated_at | timestamp |

---

# users

Default Laravel users table

---

# Optional Future Tables

## crawl_logs

| Column | Type |
|---|---|
| id | bigint |
| url | text |
| status | string |
| error_message | text |

---

# Important Notes

- ai_tags stored as JSON
- Preserve original content
- AI content separated from original