# Twig 移行進捗状況

## 概要

patTemplate から Twig への段階的移行を進行中。

## 完了項目

### Phase 1: Twig 導入準備 ✅

- [x] Twig 3.22 インストール (`composer require twig/twig`)
- [x] `src/View.php` - Twig ラッパークラス作成
- [x] `resources/views/` ディレクトリ作成
- [x] `storage/cache/twig/` キャッシュディレクトリ作成
- [x] `.gitignore` に Twig キャッシュ追加

### Phase 2: 基本テンプレート作成 ✅

- [x] `resources/views/layout/base.twig` - ベースレイアウト
  - CSS custom properties (`:root`) 対応
  - フッター、スクリプト含む
- [x] `resources/views/error.twig` - エラーページ

### Phase 3: エラーページの Twig 化 ✅

- [x] `Webapp::prterror()` を Twig 対応に修正
  - patTemplate へのフォールバック機能付き
- [x] CSS 変数の正しい展開確認
- [x] 背景色などのテーマカラー適用確認

### インフラ修正 ✅

- [x] CSS/JS パスをルート相対パス (`/css/style.css`) に修正
- [x] gzip 圧縮の二重起動防止 (`ob_get_level()` チェック)
- [x] `routes.php` で `ob_get_clean()` の `false` チェック追加
- [x] `template/template.html` に `:root` CSS 変数定義追加（patTemplate 用）
- [x] Treeview.php, Getlog.php の `Func::` 参照を Helper クラスに置換
- [x] Webapp.php の null 配列アクセス警告修正

## 未完了項目

### Phase 4: メインページの Twig 化 ⏳

- [ ] `resources/views/main.twig` - メイン BBS ページ
- [ ] `resources/views/components/message.twig` - 投稿メッセージコンポーネント
- [ ] `resources/views/components/form.twig` - 投稿フォーム
- [ ] `Bbs::prtmain()` の Twig 対応

### Phase 5: その他ページの Twig 化 ⏳

- [ ] `resources/views/follow.twig` - フォロー投稿ページ
- [ ] `resources/views/newpost.twig` - 新規投稿ページ
- [ ] `resources/views/searchlist.twig` - 検索結果ページ
- [ ] `resources/views/custom.twig` - ユーザー設定ページ
- [ ] `resources/views/postcomplete.twig` - 投稿完了ページ
- [ ] `resources/views/undocomplete.twig` - 削除完了ページ

### Phase 6: ログ・ツリービューの Twig 化 🔄

- [x] `resources/views/log/list.twig` - ログファイル一覧
- [x] `resources/views/log/searchresult.twig` - 検索結果ヘッダー
- [x] `resources/views/log/topiclist.twig` - トピック一覧
- [x] `Getlog::prtloglist()` の Twig 対応
- [x] `Getlog::prtsearchresult()` の Twig 対応
- [x] `Getlog::prttopiclist()` の Twig 対応
- [x] HTML5 time input 対応（時刻範囲検索）
- [x] ログ検索の i18n 対応
- [x] `<pre>` タグ内の空白問題修正
- [ ] `resources/views/tree/` - ツリービュー関連テンプレート
- [ ] `Treeview` クラスの Twig 対応

### Phase 7: 管理画面の Twig 化 ✅

- [x] `resources/views/admin/menu.twig` - 管理メニュー
- [x] `resources/views/admin/killlist.twig` - メッセージ削除リスト
- [x] `resources/views/admin/setpass.twig` - パスワード設定
- [x] `resources/views/admin/pass.twig` - 暗号化パスワード表示
- [x] `Bbsadmin::prtadminmenu()` の Twig 対応
- [x] `Bbsadmin::prtkilllist()` の Twig 対応
- [x] `Bbsadmin::prtsetpass()` の Twig 対応
- [x] `Bbsadmin::prtpass()` の Twig 対応
- [x] `Bbsadmin::prtlogview()` の簡略化（text/plain 出力）
- [x] 管理画面の i18n 対応（日本語・英語）
- [x] `<pre>` タグ内の空白問題修正（`{%- -%}` 使用）

### Phase 8: patTemplate 完全削除 ⏳

- [ ] 全ページの Twig 化完了確認
- [ ] `template/` ディレクトリ削除
- [ ] patTemplate 依存削除
- [ ] `Webapp::prterror()` のフォールバック削除

## 技術的な課題

### 解決済み

- ✅ CSS 変数 (`:root`) の展開方法
  - 解決策: HTML の `<style>` タグ内で `:root` を定義し、テンプレート変数を展開
  - CSS ファイルは `var(--color-background)` を使用
- ✅ 相対パスの問題
  - 解決策: `/css/style.css` のようにルート相対パスを使用
- ✅ gzip 圧縮の二重起動
  - 解決策: `ob_get_level() === 0` でチェック
- ✅ CSS ファイルの文字化け
  - 解決策: main ブランチから正しい UTF-8 ファイルを取得
- ✅ `<pre>` タグ内の空白問題
  - 解決策: Twig の空白制御構文 `{%- -%}` を使用
  - Prettier が改行を追加しても、実際の出力では空白が削除される
- ✅ `Func` クラスの参照エラー
  - 解決策: `FileHelper::getLine()` に置換
- ✅ `PHP_BBSADMIN` 定数の未定義エラー
  - 解決策: 定数削除、オートローダーで直接クラスをロード

## 開発ツール

### コードフォーマッター

- **Prettier** (Twig): `npm run format`
- **PHP-CS-Fixer** (PHP): `npm run format-php`
- **両方**: `npm run format-all`

### 設定ファイル

- `.prettierrc` - Prettier 設定（Twig 用）
- `.php-cs-fixer.php` - PHP-CS-Fixer 設定（PSR-12 準拠）

### 未解決

- ⚠️ patTemplate の複雑なネスト構造を Twig に変換する方法
- ⚠️ `visibility` 属性の扱い（Twig では `{% if %}` に変換）
- ⚠️ 動的なテンプレート切り替え（画像 BBS vs 通常 BBS）

## 現在の状態

### Twig 使用中

- エラーページ (`/?m=f&s=999` など、存在しない投稿へのアクセス)
- 管理画面
  - 管理メニュー (`/?m=ad`)
  - メッセージ削除モード (`/?m=ad&ad=k`)
  - パスワード設定 (`/?m=ad&ad=ps`)
  - 暗号化パスワード表示 (`/?m=ad&ad=p`)
  - ログファイル閲覧 (`/?m=ad&ad=l`) - text/plain 出力
- ログ検索
  - ログファイル一覧 (`/?m=g`)
  - 検索結果 (`/?m=g&f[]=20251108.dat&q=test`)
  - トピック一覧 (`/?m=g&l=20251108.dat`)

### patTemplate 使用中

- メイン BBS ページ (`/`)
- フォロー投稿ページ (`/?m=f&s=1`)
- 新規投稿ページ (`/?m=p&write=1`)
- 検索ページ (`/?m=s`, `/?m=t`)
- ツリービュー (`/?m=tree`)
- ユーザー設定ページ (`/?setup=1`)
- 投稿完了ページ
- 削除完了ページ

## 次のステップ

### 優先度: 高

1. **投稿完了・削除完了ページ** (`postcomplete`, `undocomplete`)
   - シンプルなメッセージ表示のみ
   - 移行が容易

2. **メインページの投稿表示** (`main_upper`, `main_lower`)
   - 最も使用頻度が高い
   - メッセージコンポーネント化が必要

3. **投稿フォーム** (`newpost`)
   - ユーザーインターフェースの重要部分
   - コンポーネント化推奨

### 優先度: 中

4. **検索リスト** (`searchlist_upper`, `searchlist_lower`)
5. **フォロー投稿** (`follow`)
6. **ツリービュー** (Treeview クラス)

### 優先度: 低

7. **カスタム表示** (`custom`)
8. **patTemplate 完全削除**

## 参考情報

- Twig ドキュメント: https://twig.symfony.com/
- 現在のブランチ: `twig-migration`
- ベースブランチ: `main`
