# Playground

`@wp-playground/cli` による WASM ベースの WordPress と最小 Vite フロントエンドを組み合わせた、ブラウザでプレビューのフルフローを確認するためのローカル開発環境。

---

## できること

- WordPress をローカルで起動する（Docker・PHP のインストール不要）
- ローカルのプラグインを自動でマウント・有効化する
- 起動のたびにテスト用の下書き投稿を自動生成する
- `?token=` を受け取って下書きをレンダリングする最小 HTML を Vite で配信する
- Vite から `/wp-json` を WP Playground へプロキシするため、ブラウザはクロスオリジンリクエストを送らない

---

## 動作要件

- Node.js v24 以上
- pnpm
- Composer（初回起動前に `vendor/` を生成するために必要）

---

## セットアップ

```bash
cd playground
pnpm install
pnpm exec playwright install chromium   # e2e テストを使う場合のみ
```

---

## 使い方

### 開発サーバーの起動

```bash
pnpm run dev
```

2 つのプロセスが同時に起動する。

| プロセス | URL | 役割 |
|----------|-----|------|
| WP Playground | `http://localhost:9400` | WordPress（管理画面・REST API） |
| Vite | `http://localhost:5173` | プレビューフロントエンド |

初回起動時は `predev` がプロジェクトルートで `composer install --no-dev` を実行し、WordPress が起動する前に `vendor/` が存在することを保証する。

WP Playground の初回起動は WASM 初期化と blueprint 実行のため ~60 秒かかる。2 回目以降は速い。

### blueprint が自動で行うこと

WP Playground が起動するたびに blueprint が以下を実行する。

1. `admin` / `password` でログイン
2. プラグインを `../` から `wp-content/plugins/wp-preview-token` にマウント
3. プラグインを有効化
4. プラグイン設定を構成:
   - **External Preview URL**: `http://localhost:5173`
   - **Allowed Origin**: `http://localhost:5173`
   - **Minimum Capability**: `edit_posts`
5. `Draft: Preview Test` というタイトルの下書き投稿を作成

WP Playground は再起動のたびに状態がリセットされ、blueprint が最初から再実行される。

---

## プレビューのフルフロー

1. `http://localhost:9400/wp-admin` を開く（admin / password）
2. **投稿 → 投稿一覧** を開き、`Draft: Preview Test` をクリック
3. **プレビュー → 新しいタブでプレビュー** をクリック
4. 新しいタブが開く: `http://localhost:5173?token=<64文字の16進数>`
5. プレビューページが REST API 経由で下書きコンテンツを取得してレンダリングする

---

## E2E テスト

Playwright で書かれたテストが 3 つのグループをカバーしている。

| スイート | テスト内容 |
|----------|-----------|
| `Preview page` | トークンがない・無効なときのエラー表示 |
| `REST API` | エンドポイントへの直接リクエストで `401` / `400` を確認 |
| `Full preview flow` | WP 管理画面からフロントエンドのレンダリングまでのブラウザ操作全体 |

### テストの実行

テスト実行前に両サーバーが起動している必要がある。別ターミナルで `pnpm run dev` が動いていれば、そのサーバーを再利用する（`reuseExistingServer: true`）。動いていない場合は Playwright が自動で起動する。

```bash
# すべてのテストをヘッドレスで実行
pnpm run test

# Playwright UI モードで実行（デバッグ時に便利）
pnpm run test:ui

# ブラウザを表示して実行
pnpm run test:headed
```

### 注意事項

- フルフローのテストは Gutenberg エディターを操作する。セレクターには ARIA ロールを使用しているが、WordPress のメジャーアップデートで Gutenberg の UI が変わった場合は更新が必要になることがある。
- WP Playground は再起動のたびに状態がリセットされる。`test-results/` と `playwright-report/` は gitignore 済み。
