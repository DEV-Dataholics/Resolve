from pathlib import Path
import html

base = Path(r"c:/Users/luisc/Documents/Dataholics/Dataholics Guidelines/Procesos Internos")
files = [
    ("Politicas_Operativas_Core.md", "Politicas Operativas Core", "politicas-operativas-core"),
    ("Runbook_Desarrollo_MVP_Web.md", "Runbook Desarrollo MVP Web", "runbook-desarrollo-mvp-web"),
    ("SOW.md", "SOW - Plantilla Base", "sow-plantilla-base"),
]

print("SET NAMES utf8mb4;")
print()

for fn, title, slug in files:
    raw = (base / fn).read_text(encoding="utf-8")
    html_content = (
        '<pre style="white-space:pre-wrap;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace;">'
        + html.escape(raw)
        + "</pre>"
    )
    hx = html_content.encode("utf-8").hex().upper()
    title_sql = title.replace("'", "''")
    slug_sql = slug.replace("'", "''")
    category_sql = "Procesos Internos"

    print("INSERT INTO kb_articles (title, slug, category, content, status, author_id, created_at, updated_at)")
    print(f"VALUES ('{title_sql}', '{slug_sql}', '{category_sql}', 0x{hx}, 'published', NULL, NOW(), NOW())")
    print("ON DUPLICATE KEY UPDATE")
    print("  title = VALUES(title),")
    print("  category = VALUES(category),")
    print("  content = VALUES(content),")
    print("  status = VALUES(status),")
    print("  updated_at = NOW();")
    print()
