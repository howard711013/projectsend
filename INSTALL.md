# ProjectSend 安裝指南

本文件整理本專案（ProjectSend）的安裝方式，先說明系統需求與安裝概覽，再以**從原始碼開發建置**為主要流程，接續網頁安裝精靈與手動設定等步驟。

更多說明亦可參考官方文件：[docs.projectsend.org](https://docs.projectsend.org)

---

## 目錄

1. [系統需求](#系統需求)
2. [安裝方式概覽](#安裝方式概覽)
3. [從原始碼開發建置](#從原始碼開發建置)
4. [網頁安裝精靈](#網頁安裝精靈)
5. [手動設定資料庫](#手動設定資料庫)
6. [目錄與權限](#目錄與權限)
7. [Web 伺服器設定](#web-伺服器設定)
8. [PHP 建議設定](#php-建議設定)
9. [安裝後設定](#安裝後設定)
10. [升級既有安裝](#升級既有安裝)
11. [常見問題](#常見問題)

---

## 系統需求

| 項目 | 最低 / 建議版本 |
|------|----------------|
| **PHP** | 8.2 或以上（`composer.json` 與 `includes/app.php` 定義） |
| **資料庫** | MySQL 5.7+ 或 MariaDB 10.3+（建議；README 說明） |
| **Web 伺服器** | Apache 或 Nginx |
| **Composer** | [從原始碼開發建置](#從原始碼開發建置) 時需要 |
| **Node.js** | 建置前端資源時需要（建議 18.x / 20.x） |

### 必要 PHP 擴充套件

依 `composer.json` 與安裝程式檢查：

| 擴充套件 | 用途 |
|--------|------|
| **pdo** + **pdo_mysql** | 資料庫連線（MySQL / MariaDB） |
| **json** | JSON 處理 |
| **exif** | 圖片相關處理 |

### 建議安裝的 PHP 擴充套件

| 擴充套件 | 用途 |
|--------|------|
| **gd** | 縮圖產生（未安裝時部分圖片功能受限） |
| **zip** | 內建自動更新、ZIP 下載等功能 |
| **mbstring** | 字串處理 |
| **openssl** | 加密與 HTTPS 相關功能 |

### 資料庫驅動支援

- **MySQL / MariaDB**（主要支援，透過 PDO `mysql` 驅動）
- **MS SQL Server**（可選，需 PDO `dblib` 驅動；安裝精靈中可選）

### 其他需求

- 可寫入的設定檔與上傳目錄（見下方 [目錄與權限](#目錄與權限)）
- 建議使用 **HTTPS**（Session Cookie 會依 HTTPS 自動設定 `secure` 旗標）

---

## 安裝方式概覽

| 方式 | 適用對象 | 說明 |
|------|----------|------|
| **原始碼 + 建置** | 開發者、貢獻者（主要） | Clone 儲存庫後執行 `composer install` 與 `gulp build`（見下方章節） |
| **網頁安裝精靈** | 建置完成後 | 由瀏覽器完成資料庫與管理員設定 |
| **一鍵安裝** | 支援的主機商 | 透過 **Softaculous**、**Installatron** 等面板安裝 |

---

## 從原始碼開發建置

適用於 clone 本儲存庫進行開發或自訂建置，**不建議**直接將未建置的原始碼部署至正式環境。

### 前置工具

- PHP 8.2+
- [Composer](https://getcomposer.org/)
- Node.js 18+ 與 npm
- MySQL / MariaDB（本機或 Docker 等）

### 步驟

```bash
# 1. 取得原始碼
git clone https://github.com/projectsend/projectsend.git
cd projectsend

# 2. PHP 依賴
composer install

# 3. 前端依賴與資源建置
npm ci
npx gulp build
# 或分開執行：npx gulp sass && npx gulp javascript

# 4. 設定資料庫（複製並編輯設定檔，或透過瀏覽器安裝）
cp includes/sys.config.sample.php includes/sys.config.php
# 編輯 includes/sys.config.php

# 5. 設定 Web 伺服器指向專案根目錄，開啟 install/make-config.php
```

### 開發時重建前端

修改 `assets/src/` 後：

```bash
npx gulp sass        # 僅 SCSS
npx gulp javascript  # 僅 JS
npx gulp build       # 完整建置
```

### 靜態分析（可選）

```bash
composer phpstan
```

---

## 網頁安裝精靈

安裝分兩個階段，由 `includes/app.php` 在未偵測到 `includes/sys.config.php` 時自動導向。

### 階段一：資料庫與環境檢查

**URL：** `https://your-site.example/install/make-config.php`

此頁面會檢查並設定：

1. **資料庫連線** — 主機、連接埠、資料庫名稱、帳號、密碼、資料表前綴（預設 `tbl_`）
2. **PDO 驅動** — MySQL 或 MS SQL（依伺服器可用驅動）
3. **語言** — 預設介面語言
4. **上傳大小上限** — 單檔最大 MB（預設 2048，需與 PHP `upload_max_filesize` 等設定一致）
5. **目錄可寫性** — 設定檔、`upload/files`、`upload/temp`
6. **系統資訊** — PHP 版本、記憶體限制、POST / 上傳大小等

檢查通過後，點選 **Write config file** 會：

- 依 `includes/sys.config.sample.php` 產生 `includes/sys.config.php`
- 自動產生 `ENCRYPTION_MASTER_KEY`（檔案加密功能使用，**安裝後請勿隨意變更**）

### 階段二：站台與管理員

**URL：** `https://your-site.example/install/index.php`

填寫：

| 欄位 | 說明 |
|------|------|
| Site name | 站台名稱（後台與客戶端顯示） |
| ProjectSend URI | 完整網址路徑，**必須以 `/` 結尾**（例如 `https://example.com/projectsend/`） |
| 管理員姓名、Email、帳號、密碼 | 第一位系統管理員（安裝後無法刪除） |

提交後安裝程式會：

- 建立資料表並寫入初始資料
- 執行資料庫升級腳本
- 建立 `upload/`、`upload/temp/`、`upload/files/`、`upload/thumbnails/`（權限 755）
- 導向 `install/install-success.php`

完成後使用剛建立的管理員帳號登入。

### 已安裝保護

若系統已安裝，再存取 `install/` 會導向 `install/already-installed.php`，避免重複安裝。

---

## 手動設定資料庫

若偏好手動設定、或無法使用安裝精靈的「寫入設定檔」步驟：

1. 複製範例設定檔：

   ```bash
   cp includes/sys.config.sample.php includes/sys.config.php
   ```

2. 編輯 `includes/sys.config.php`，至少設定：

   - `DB_DRIVER`、`DB_NAME`、`DB_HOST`、`DB_PORT`
   - `DB_USER`、`DB_PASSWORD`
   - `TABLES_PREFIX`（預設 `tbl_`）
   - `SITE_LANG`、`MAX_FILESIZE`

3. **加密金鑰**（建議）：`ENCRYPTION_MASTER_KEY` 應為 base64 編碼的 32 位元組金鑰。可透過安裝精靈自動產生，或自行產生後寫入（生產環境請妥善備份）。

4. 在瀏覽器開啟 `install/index.php` 完成站台名稱與管理員建立（仍需可連線的資料庫與可寫入的 `upload/`）。

---

## 目錄與權限

### 安裝前需可寫入

| 路徑 | 說明 |
|------|------|
| `includes/sys.config.php` 或其父目錄 `includes/` | 產生設定檔 |
| `upload/` 或 `upload/files/`、`upload/temp/` | 檔案上傳與暫存 |

### 安裝後由程式建立（若不存在）

```
upload/
upload/temp/
upload/files/
upload/thumbnails/
```

建議權限：**755**（目錄）、Web 伺服器使用者需具備寫入權限。

### 範例（Linux，依實際 Web 使用者調整）

```bash
# 常見為 www-data（Debian/Ubuntu）或 apache（RHEL/CentOS）
chown -R www-data:www-data upload includes/sys.config.php
chmod 755 upload upload/files upload/temp upload/thumbnails
chmod 644 includes/sys.config.php
```

`upload/` 內含 `.htaccess`，限制直接執行腳本，請勿刪除。

---

## Web 伺服器設定

### Apache

- 確保已啟用 `mod_rewrite`（專案根目錄 `.htaccess` 預設 `RewriteEngine Off`，多數環境以實體 PHP 路徑存取即可）
- 文件根目錄（DocumentRoot）指向 ProjectSend 專案根目錄
- 允許 `.htaccess` 覆寫（`AllowOverride`）若需使用目錄內安全規則

### Nginx

將 `root` 指向 ProjectSend 目錄，並將 PHP 交給 PHP-FPM，例如：

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/projectsend;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

子目錄安裝時，請將 **ProjectSend URI** 設為含子路徑且結尾為 `/` 的完整 URL。

### HTTPS

正式環境強烈建議啟用 HTTPS，以符合 Session Cookie 安全設定與檔案傳輸需求。

---

## PHP 建議設定

依安裝精靈顯示的系統資訊調整 `php.ini`（或主機面板中的 PHP 選項）：

| 設定項 | 建議 |
|--------|------|
| `memory_limit` | 128M 以上（大量上傳或縮圖時可提高） |
| `upload_max_filesize` | 不小於 `MAX_FILESIZE`（`sys.config.php` 中，單位 MB） |
| `post_max_size` | 略大於 `upload_max_filesize` |
| `max_execution_time` | 大檔上傳時可適度提高 |

`composer.json` 中的 `MAX_FILESIZE` 與 PHP 限制需一致，否則大檔仍無法上傳。

---

## 安裝後設定

登入管理後台後建議檢查：

1. **Options（選項）** — 時區、日期格式、縮圖、允許的副檔名、郵件寄件設定
2. **Security（安全性）** — 2FA、檔案加密、REST API 上傳等
3. **郵件** — 確認可寄送通知信（新檔案、新客戶等）
4. **備份** — 備份資料庫、`includes/sys.config.php`（含 `ENCRYPTION_MASTER_KEY`）與 `upload/files/`

### REST API 檔案上傳（可選）

若需使用 API 上傳，請參考 [`docs/API-UPLOAD.md`](docs/API-UPLOAD.md)：

1. 資料庫升級會在首次載入時自動執行
2. 後台 **Options → Security** 啟用 REST API
3. 使用者在 **My Account → API keys** 建立金鑰

---

## 升級既有安裝

- **建議：** 在管理後台使用內建自動更新（需 `zip` 擴充套件，且僅從官方來源下載）。
- **手動：** 參考 [官方升級說明](https://www.projectsend.org/documentation/)。

升級時 **`includes/sys.config.php` 不會被覆蓋**，但請先備份資料庫與 `upload/`。

---

## 常見問題

### 開啟網站後一直導向 `install/make-config.php`

- 尚未產生 `includes/sys.config.php`：完成階段一安裝，或手動從 `sys.config.sample.php` 建立。
- `includes/` 不可寫：修正目錄權限後重試「Write config file」。

### 資料庫連線失敗

- 確認主機、連接埠（預設 MySQL `3306`）、帳密、資料庫名稱。
- Docker 或非本機 DB 時，主機可能需填服務名稱或 IP，而非 `localhost`。
- 確認 PHP 已安裝 `pdo_mysql`。

### 上傳失敗或檔案過小

- 比對 `MAX_FILESIZE`（`sys.config.php`）與 `upload_max_filesize`、`post_max_size`。
- 確認 `upload/files/`、`upload/temp/` 可寫。

### 安裝成功但出現目錄 / chmod 警告

資料表已建立，但部分目錄未建立或無法設為 755。請手動建立 `upload/` 子目錄並設定權限後再試上傳。

### PHP 版本不符

系統要求 PHP **8.2+**（`REQUIRED_VERSION_PHP`）。低於此版本會被導向需求檢查頁面。

### 重複安裝

已安裝後請勿刪除資料表重新安裝同一前綴；必要時使用新資料表前綴或清空資料庫後再安裝。

---

## 相關連結

- [ProjectSend 官網](https://www.projectsend.org)
- [官方文件](https://docs.projectsend.org)
- [GitHub Releases](https://github.com/projectsend/projectsend/releases/latest)
- [線上 Demo](https://www.projectsend.org/demo/)（無需安裝即可試用）
- [翻譯貢獻（Transifex）](https://explore.transifex.com/subwaydesign/projectsend/)

---

## 授權

ProjectSend 採用 [GNU GPL v2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html) 授權（詳見 README）。
