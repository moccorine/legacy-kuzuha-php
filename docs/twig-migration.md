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

### Phase 6: ログ・ツリービューの Twig 化 ⏳

- [ ] `resources/views/log/` - ログ検索関連テンプレート
- [ ] `resources/views/tree/` - ツリービュー関連テンプレート
- [ ] `Getlog` クラスの Twig 対応
- [ ] `Treeview` クラスの Twig 対応

### Phase 7: 管理画面の Twig 化 ⏳

- [ ] `resources/views/admin/` - 管理画面テンプレート
- [ ] `Bbsadmin` クラスの Twig 対応

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

### 未解決

- ⚠️ patTemplate の複雑なネスト構造を Twig に変換する方法
- ⚠️ `visibility` 属性の扱い（Twig では `{% if %}` に変換）
- ⚠️ 動的なテンプレート切り替え（画像 BBS vs 通常 BBS）

## 現在の状態

### Twig 使用中

- エラーページ (`/?m=f&s=999` など、存在しない投稿へのアクセス)

### patTemplate 使用中

- メイン BBS ページ (`/`)
- フォロー投稿ページ (`/?m=f&s=1`)
- 新規投稿ページ (`/?m=p&write=1`)
- 検索ページ (`/?m=g`, `/?m=s`, `/?m=t`)
- ツリービュー (`/?m=tree`)
- ユーザー設定ページ (`/?setup=1`)
- 管理画面 (`/admin`)

## 次のステップ

1. メインページの投稿メッセージ表示部分を Twig 化
2. 投稿フォームをコンポーネント化
3. 各完了ページ（postcomplete, undocomplete）を Twig 化
4. 検索・ツリービューを Twig 化
5. 管理画面を Twig 化
6. patTemplate 完全削除

## 参考情報

- Twig ドキュメント: https://twig.symfony.com/
- 現在のブランチ: `twig-migration`
- ベースブランチ: `main`
