# Preview Token

## 概要

ヘッドレスフロントエンド向けに、任意の期限付きプレビュートークンを発行する WordPress プラグイン。

権限のある WordPress ユーザーが Gutenberg サイドバー、クイック編集パネル、またはクラシックエディターのメタボックスからトークンを生成する。プラグインはトークンを設定済みの外部フロントエンド URL に埋め込んだプレビュー URL を生成する。フロントエンドはそのトークンで REST エンドポイントを呼び出し、下書きの投稿データを受け取る。

**解決すること:**

- フロントエンドは WordPress の認証情報を保持せずに下書きコンテンツにアクセスできる
- トークンの有効期限は設定可能（1時間 / 24時間 / 30日 / カスタム / 無期限）
- スコープはプレビューのみ — WordPress の広範な権限は付与されない
- フロントエンドは URL パラメータを透過的に扱うだけ — コードや設定にシークレットを埋め込まない

---

## 仕組み

### フロー

```
エディターが Gutenberg サイドバー / クイック編集 / クラシックエディターでトークンを生成
  └─ プラグインがトークンを発行 → SHA-256 ハッシュを wp_options に保存（pvt_tk_{hash}）
  └─ プレビュー URL を生成: https://front.example.com/preview?token=<64文字のhex>

ユーザーがブラウザでプレビュー URL を開く

フロントエンド
  └─ GET /wp-json/preview-token/v1/preview?token=<token>

WordPress
  └─ トークンをハッシュ化して wp_options のキーを引く
  └─ 有効期限を検証
  └─ 投稿データを返却（WP REST API フォーマット）

フロントエンドがプレビューをレンダリング
```

### トークン仕様

| 項目     | 内容                                                                   |
|----------|------------------------------------------------------------------------|
| 生成方法 | `bin2hex(random_bytes(32))` — 256-bit CSPRNG、64文字の16進数            |
| 保存先   | `wp_options`、キー = `pvt_tk_` + `sha256(token)`（O(1) ルックアップ） |
| 有効期限 | 設定可能: 1時間 / 24時間 / 30日 / カスタム日時 / 無期限               |
| 再利用   | 有効期限内は複数回利用可                                               |
| 失効時   | `401 Unauthorized`（存在しない場合と同一）                             |

`wp_options` に保存される検索キーは raw トークンではなくその SHA-256 ハッシュ値。DB が漏洩してもトークン文字列が直接露出しない。

有効期限内の再利用を許可しているのは、エディターがプレビュータブをリロードしたり複数のビューポートで確認したりするケースに対応するため。

### REST エンドポイント

**プレビュー（公開）**
```
GET /wp-json/preview-token/v1/preview?token=<token>
```

| ステータス | 条件                         |
|------------|------------------------------|
| `200`      | トークン有効、投稿あり       |
| `401`      | トークンが無効または失効済み |
| `400`      | token パラメータなし         |
| `403`      | HTTPS 必須                   |
| `404`      | 投稿が見つからない           |
| `429`      | レート制限超過               |

**トークン管理（認証済み）**
```
POST   /wp-json/preview-token/v1/token   # 発行
GET    /wp-json/preview-token/v1/token   # 現在のトークン取得
PATCH  /wp-json/preview-token/v1/token   # 有効期限のみ更新
DELETE /wp-json/preview-token/v1/token   # 失効
```

レスポンスボディは WordPress 標準の REST API フォーマット（`/wp/v2/posts/{id}`）に準拠する。以下のフィールドはレスポンス前に除去される。

| 除去フィールド | 理由                    |
|----------------|-------------------------|
| `password`     | 機密情報                |
| `guid`         | WP 内部フィールド       |
| `ping_status`  | プレビューに不要        |
| `template`     | プレビューに不要        |

`pvt_preview_response_data` フィルターでレスポンスのフィールドを追加・除去・変換できる。

```php
// フィールドを除去する
add_filter('pvt_preview_response_data', function (array $data, WP_Post $post, WP_REST_Request $req): array {
    unset($data['author']);
    return $data;
}, 10, 3);

// ACF フィールドを追加する（「REST API に表示」が有効なフィールドは自動で含まれる。
// auth_callback で権限が要求されているフィールドなど、未認証コンテキストで除外されるものにのみ使用する）
add_filter('pvt_preview_response_data', function (array $data, WP_Post $post): array {
    $data['acf'] = function_exists('get_fields') ? get_fields($post->ID) : [];
    return $data;
}, 10, 2);

// content.raw（ブロックのマークアップ）を追加する（トークンの受け取り側を信頼できる場合のみ）
add_filter('pvt_preview_response_data', function (array $data, WP_Post $post): array {
    $data['content']['raw'] = $post->post_content;
    return $data;
}, 10, 2);
```

**ACF・カスタム REST フィールドについて:** `register_rest_field()` や ACF の「REST API に表示」で登録したフィールドはフィルター不要で自動的にレスポンスに含まれる。フィルターが必要なのは、`auth_callback` に権限チェックが設けられているなど、未認証コンテキストで除外されるフィールドに限る。

### CORS

許可オリジンは **設定 → Preview Token → 許可オリジン (CORS)** で設定する。複数オリジンとワイルドカードパターン（`https://*.example.com`）に対応。プラグインは `rest_pre_serve_request` を priority 11 で実行し、非許可オリジンに対する WP コアの無条件エコーバックを上書きする。

---

## はじめに

### 動作要件

- PHP 7.4 以上
- WordPress 5.9 以上
- Composer

### インストール

```bash
git clone https://github.com/uuki/preview-token
cd preview-token
composer install --no-dev
```

プラグインディレクトリを `wp-content/plugins/preview-token` にコピー（またはシンボリックリンク）し、WordPress 管理画面の **プラグイン** から有効化する。

### 設定

**設定 → Preview Token** を開く。

| 項目                    | 説明                                                                                         |
|-------------------------|----------------------------------------------------------------------------------------------|
| External Preview URL    | フロントエンドのベース URL。「外部プレビューを開く」クリック時の遷移先になる。               |
| 許可オリジン (CORS)     | 1行1オリジン。ワイルドカード対応（`https://*.example.com`）。空欄で CORS ヘッダーを無効化。 |
| 最低ロール              | トークン発行に必要な最低 WordPress ロール。デフォルト: `contributor`。                       |
| レート制限              | IP 単位の最大リクエスト数 / ウィンドウ。デフォルト: 30 req / 60 s。                         |
| 有効期限なしトークンを許可 | 有効期限のないトークンの発行を許可する。デフォルト: 無効。                                 |
| HTTPS チェックをスキップ | HTTPS 強制を無効化する。開発環境専用。                                                       |

### フロントエンド実装

受信した URL から `token` を読み取り、REST エンドポイントに転送するだけでよい。フロントエンド側に認証情報は不要。

```typescript
// Astro: src/pages/preview.astro
const token = Astro.url.searchParams.get('token');

if (!token) return Astro.redirect('/404');

const res = await fetch(
  `https://wp.example.com/wp-json/preview-token/v1/preview?token=${token}`
);

if (!res.ok) return Astro.redirect('/404');

const post = await res.json();
```
