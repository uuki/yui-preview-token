# WP Preview Token

## 概要

ヘッドレスフロントエンド向けに、短命なプレビュートークンを発行する WordPress プラグイン。

WordPress 管理画面でエディターが **プレビュー** をクリックすると、プラグインが一時トークンを生成し、そのトークンを URL に付与した状態でフロントエンドを別タブで開く。フロントエンドはそのトークンをプラグインの REST エンドポイントに転送し、WordPress がトークンを検証して下書きの投稿データを返す。

**解決すること:**

- フロントエンドは WordPress の認証情報を保持せずに下書きコンテンツにアクセスできる
- トークンは 5 分で失効する — URL が漏洩した際の影響を限定できる
- スコープはプレビューのみ — WordPress の広範な権限は付与されない
- フロントエンドは URL パラメータを透過的に扱うだけ — コードや設定にシークレットを埋め込まない

---

## 仕組み

### フロー

```
エディターが WP 管理画面でプレビューをクリック
  └─ プラグインがトークンを発行 → Transient に保存（TTL 300s）
  └─ 別タブが開く: https://front.example.com/preview?token=<token>

フロントエンドがリクエストを受信
  └─ GET /wp-json/wp-preview-token/v1/preview?token=<token>

WordPress
  └─ Transient でトークンを検証
  └─ 投稿データを返却（WP REST API フォーマット）

フロントエンドがプレビューをレンダリング
```

### トークン仕様

| 項目     | 内容                                      |
|----------|-------------------------------------------|
| 生成方法 | `bin2hex(random_bytes(32))` — 256-bit hex |
| 保存先   | WordPress Transients                      |
| TTL      | 300 秒                                    |
| 再利用   | TTL 内は複数回利用可                      |
| 失効時   | `401 Unauthorized`（存在しない場合と同一）|

TTL 内の再利用を許可しているのは、エディターがプレビュータブをリロードしたり、複数のビューポートで確認したりするケースに対応するためである。

### REST エンドポイント

```
GET /wp-json/wp-preview-token/v1/preview?token=<token>
```

| ステータス | 条件                         |
|------------|------------------------------|
| `200`      | トークン有効、投稿あり       |
| `401`      | トークンが無効または失効済み |
| `404`      | 投稿が見つからない           |

レスポンスボディは WordPress 標準の REST API フォーマット（`/wp/v2/posts/{id}`）に準拠する。以下のフィールドはレスポンス前に除去される。

| 除去フィールド | 理由                    |
|----------------|-------------------------|
| `password`     | 機密情報                |
| `guid`         | WP 内部フィールド       |
| `ping_status`  | プレビューに不要        |
| `template`     | プレビューに不要        |

追加のフィールドを除去したい場合は、`ResponsePipeline` にカスタムフィルター関数を登録する。

### CORS

許可オリジンが設定されている場合、プラグインはプレビューエンドポイントのレスポンスにのみ `Access-Control-Allow-Origin` を付与する。他の WordPress REST ルートには影響しない。

---

## はじめに

### 動作要件

- PHP 7.4 以上
- WordPress 5.6 以上
- Composer

### インストール

```bash
git clone https://github.com/uuki/wp-preview-token
cd wp-preview-token
composer install --no-dev
```

プラグインディレクトリを `wp-content/plugins/wp-preview-token` にコピーし、WordPress 管理画面の **プラグイン** から有効化する。

### 設定

**設定 → Preview Token** を開く。

| 項目                  | 説明                                                                             |
|-----------------------|----------------------------------------------------------------------------------|
| External Preview URL  | 外部クライアント（headless フロントエンド等）のプレビュー URL（例: `https://front.example.com/preview`）  |
| Allowed Origin (CORS) | CORS で許可するオリジン。空欄の場合は CORS ヘッダーを付与しない                  |
| Minimum Capability    | トークン発行に必要な最小 Capability。デフォルト: `edit_posts`                    |

### フロントエンド実装

受信した URL から `token` を読み取り、REST エンドポイントに転送するだけでよい。フロントエンド側に認証情報は不要。

```typescript
// Astro: src/pages/preview.astro
const token = Astro.url.searchParams.get('token');

if (!token) return Astro.redirect('/404');

const res = await fetch(
  `https://wp.example.com/wp-json/wp-preview-token/v1/preview?token=${token}`
);

if (!res.ok) return Astro.redirect('/404');

const post = await res.json();
```
