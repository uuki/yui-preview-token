# Playground

`@wp-playground/cli` による WASM ベースの WordPress と最小 Vite フロントエンドを組み合わせた、ブラウザでプレビューのフルフローを確認するためのローカル開発環境。

---

## できること

- WordPress をローカルで起動する（Docker・PHP のインストール不要）
- ローカルのプラグインを自動でマウント・有効化する
- Plugin Check をインストールし、管理画面から準拠チェックを実行できる
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

**プロジェクトルート**から実行する:

```bash
pnpm run dev
```

2 つのプロセスが同時に起動する。

| プロセス | URL | 役割 |
|----------|-----|------|
| WP Playground | `http://127.0.0.1:9400` | WordPress（管理画面・REST API） |
| Vite | `http://localhost:5173` | プレビューフロントエンド |

初回起動時は `predev` がプロジェクトルートで `composer install --no-dev` を実行し、WordPress が起動する前に `vendor/` が存在することを保証する。

WP Playground の初回起動は WASM 初期化と blueprint 実行のため ~60 秒かかる。2 回目以降は速い。

> **注意:** WP のロケールによってログインページのラベルが変わる。テストでは `#user_login` / `#user_pass` などの安定した element ID を使用する。

### blueprint が自動で行うこと

WP Playground が起動するたびに blueprint が以下を実行する。

1. `preview-token` プラグインを有効化（`../` を `preview-token/` としてマウント）
2. Plugin Check を WordPress.org からインストール
3. クラシックエディターテスト用フィクスチャプラグインを作成（E2E テストで使用）
4. プラグイン設定を構成:
   - **External Preview URL**: `http://localhost:5173`
   - **許可オリジン (CORS)**: `http://localhost:4321`
   - **最低ロール**: `contributor`
   - **レート制限**: 60 req / 60 s
5. `Draft: Preview Test` というタイトルの下書き投稿を作成

WP Playground は再起動のたびに状態がリセットされ、blueprint が最初から再実行される。

---

## プレビューのフルフロー

1. `http://127.0.0.1:9400/wp-admin` を開く（admin / password）
2. **投稿 → 投稿一覧** を開き、`Draft: Preview Test` を Gutenberg で開く
3. **External Preview** サイドバーパネルで有効期限を選択し **トークンを生成** をクリック
4. **外部プレビューを開く** をクリック
5. 新しいタブが開く: `http://localhost:5173/preview?token=<64文字の16進数>`
6. プレビューページが REST API 経由で下書きコンテンツを取得してレンダリングする

トークン管理は投稿一覧の**クイック編集**パネル、およびクラシックエディタープラグインが有効な場合は**クラシックエディター**のメタボックスからも操作できる。

---

## E2E テスト

テストは Playwright で書かれている。プロジェクトルートから実行する:

```bash
pnpm run test          # ヘッドレス
pnpm run test:ui       # Playwright UI モード（デバッグ時に便利）
pnpm run test:headed   # ブラウザを表示して実行
```

テスト実行前に両サーバーが起動している必要がある。別ターミナルで `pnpm run dev` が動いていれば、そのサーバーを再利用する。

### テストスイート

| ファイル | テスト内容 |
|----------|-----------|
| `preview.spec.js` | プレビューページのエラー表示; Gutenberg / クイック編集 / クラシックエディターのフルフロー |
| `permissions.spec.js` | ロール別トークン発行（subscriber → administrator） |
| `cors-ui.spec.js` | 設定画面の CORS オリジン動的入力リスト |
| `security.spec.js` | OWASP Top 10 ブラックボックス攻撃ベクター（33 テスト） |

### WP Playground 固有の制約

| 問題 | 原因 | 対処 |
|------|------|------|
| Gutenberg が profile.php にリダイレクトされる | `[aria-expanded="false"]` を全部クリックするとツールバーメニューが開いてページ遷移 | `.editor-sidebar` 内のボタンのみ展開する |
| `waitForFunction` でページコンテキストが破棄される | WP Playground WASM が長時間の JS ポーリング中にコンテキストを破棄 | Gutenberg テストでは `waitForFunction` の代わりに固定 `waitForTimeout` を使用 |
| ロケールによりログインラベルが変わる | WP がフォームラベルを翻訳する | `#user_login` / `#user_pass` / `#wp-submit` の ID を使用 |
| Plugin Check が開発用 dotfile を検出する | `plugin_basename()` が `realpath()` を呼びシンボリックリンクを解決してプロジェクトルートをスキャン | Plugin Check は `dist/preview-token.zip` に対してのみ実行する |
