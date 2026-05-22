# API file upload

## Enable

1. Run database upgrades (automatic on next page load).
2. Go to **Options → Security** and enable **REST API file upload**.
3. Each user creates keys under **My Account → API keys**.

## Upload a file

```bash
curl -X POST "https://your-site.example/api/v1/files" \
  -H "Authorization: Bearer ps_live_xxxxxxxx" \
  -F "file=@/path/to/document.pdf" \
  -F "description=Optional description" \
  -F "title=Optional display title" \
  -F "group_ids[]=1" \
  -F "group_ids[]=2"
```

### Assign to client groups

Use `group_ids[]` (repeat for multiple groups) or a comma-separated `group_ids` field.

- Requires **edit files** and **manage groups** permissions on the token owner account.
- Group IDs must exist and be allowed for the user (respects upload-to-client limits).

### Optional fields

| Field | Description |
|-------|-------------|
| `filename` | Override original filename |
| `storage` | `local` or integration ID (needs upload storage select permission) |
| `encrypt` | `1` to encrypt when encryption is enabled |

## Success response (201)

```json
{
  "status": "success",
  "data": {
    "id": 123,
    "filename": "document.pdf",
    "size": 1048576,
    "public_token": "...",
    "encrypted": 0,
    "download_url": "https://your-site.example/download.php?id=123",
    "groups_assigned": [1, 2],
    "groups_invalid": []
  }
}
```

## Errors

| HTTP | code | Meaning |
|------|------|---------|
| 401 | `invalid_token` | Missing/invalid Bearer token |
| 403 | `forbidden` | No upload or cannot assign groups |
| 422 | `invalid_group` | Unknown or disallowed group ID |
| 503 | `api_disabled` | API turned off in options |
