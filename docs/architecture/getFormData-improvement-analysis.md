# getFormData メソッド改善分析

## 現状

**場所**: `src/Kuzuha/Bbs.php:465-603` (約140行)

**責任**:
- フォームのデフォルト値設定
- セキュリティトークン生成
- カウンター/統計情報取得
- チェックボックス状態計算
- 表示フラグ計算
- 顔文字ボタン生成
- 翻訳文字列の大量マッピング（約50個）

## 問題点

### 1. 単一責任の原則違反
メソッドが多すぎる責任を持っている：
- データ取得（カウンター、統計）
- ビジネスロジック（表示フラグ計算）
- プレゼンテーション（翻訳文字列マッピング）

### 2. 翻訳文字列の冗長性
50個以上の `TRANS_*` キーを手動でマッピング：
```php
'TRANS_NAME' => Translator::trans('form.name'),
'TRANS_EMAIL' => Translator::trans('form.email'),
// ... 50+ more
```

これは Twig 側で直接 `trans('form.name')` を呼べば不要。

### 3. テスタビリティの低さ
- 140行のメソッドは単体テストが困難
- 複数の依存関係（config, session, repositories）
- モックが必要な箇所が多い

### 4. 保守性の低さ
- 新しいフォームフィールドを追加するたびに肥大化
- 翻訳キーの追加漏れが発生しやすい
- 変更の影響範囲が不明確

## 改善案

### 案1: メソッド分割（短期・影響小）

```php
protected function getFormData($dtitle, $dmsg, $dlink, $mode = '')
{
    return array_merge(
        $this->config,
        $this->session,
        $this->getFormDefaults($dtitle, $dmsg, $dlink, $mode),
        $this->getFormSecurity(),
        $this->getFormCounters(),
        $this->getFormCheckboxes(),
        $this->getFormVisibility(),
        $this->getFormKaomoji(),
        $this->getFormTranslations()
    );
}

private function getFormDefaults($dtitle, $dmsg, $dlink, $mode): array
{
    return [
        'MODE' => $mode ?: 'p',
        'DTITLE' => $dtitle,
        'DMSG' => $dmsg,
        'DLINK' => $dlink,
    ];
}

private function getFormSecurity(): array
{
    return [
        'PCODE' => SecurityHelper::generateProtectCode(),
    ];
}

private function getFormCounters(): array
{
    $data = [];
    
    if ($this->config['SHOW_COUNTER'] && $this->accessCounterRepo) {
        $data['SHOW_COUNTER'] = true;
        $data['COUNTER'] = number_format($this->accessCounterRepo->getCurrent());
    }
    
    if ($this->config['CNTFILENAME'] && $this->participantCounterRepo) {
        $data['SHOW_MBRCOUNT'] = true;
        $data['MBRCOUNT'] = number_format(
            $this->participantCounterRepo->getActiveCount(CURRENT_TIME, $this->config['CNTLIMIT'])
        );
    }
    
    return $data;
}

// ... 他のヘルパーメソッド
```

**メリット**:
- 各メソッドが単一責任
- テストしやすい
- 段階的に実装可能

**デメリット**:
- メソッド数が増える
- 根本的な問題（翻訳文字列）は解決しない

### 案2: 翻訳文字列をTwig側に移動（中期・影響中）

**現在（PHP側）**:
```php
'TRANS_NAME' => Translator::trans('form.name'),
'TRANS_EMAIL' => Translator::trans('form.email'),
```

**改善後（Twig側）**:
```twig
{{ trans('form.name') }}
{{ trans('form.email') }}
```

または Twig 関数を拡張：
```twig
{{ form_trans('name') }}  {# 自動的に 'form.' プレフィックス #}
```

**メリット**:
- PHP コードが50行削減
- 翻訳キーの追加が容易
- テンプレート側で翻訳を管理

**デメリット**:
- 全テンプレートの修正が必要
- 破壊的変更

### 案3: FormDataBuilder サービス作成（長期・影響大）

```php
class FormDataBuilder
{
    public function __construct(
        private array $config,
        private array $session,
        private ?AccessCounterRepositoryInterface $accessCounterRepo,
        private ?ParticipantCounterRepositoryInterface $participantCounterRepo
    ) {}
    
    public function build(string $title, string $msg, string $link, string $mode = 'p'): array
    {
        return [
            ...$this->config,
            ...$this->session,
            ...$this->buildDefaults($title, $msg, $link, $mode),
            ...$this->buildCounters(),
            ...$this->buildCheckboxes(),
            ...$this->buildVisibility(),
        ];
    }
    
    // ... private methods
}

// Bbs.php
protected function getFormData($dtitle, $dmsg, $dlink, $mode = '')
{
    $builder = new FormDataBuilder(
        $this->config,
        $this->session,
        $this->accessCounterRepo,
        $this->participantCounterRepo
    );
    
    return $builder->build($dtitle, $dmsg, $dlink, $mode);
}
```

**メリット**:
- 完全な責任分離
- 単体テストが容易
- 再利用可能

**デメリット**:
- 新しいクラスの追加
- 大規模なリファクタリング

## 推奨アプローチ

**段階的実施**:

1. **Phase 1（今すぐ）**: DocBlock 改善 ✅ 完了
2. **Phase 2（次回）**: メソッド分割（案1）
3. **Phase 3（将来）**: 翻訳文字列を Twig に移動（案2）
4. **Phase 4（v2.0）**: FormDataBuilder サービス作成（案3）

## 即座に実施可能な小改善

### チェックボックス状態の簡略化

**現在**:
```php
$chkA = $this->config['AUTOLINK'] ? ' checked="checked"' : '';
$chkHide = $this->config['HIDEFORM'] ? ' checked="checked"' : '';
```

**改善後**:
```php
// Twig 側で判定
'AUTOLINK' => (bool) $this->config['AUTOLINK'],
'HIDEFORM' => (bool) $this->config['HIDEFORM'],
```

Twig:
```twig
<input type="checkbox" {{ AUTOLINK ? 'checked' : '' }}>
```

### 表示フラグの統合

**現在**:
```php
$showFormConfig = ($this->config['BBSMODE_ADMINONLY'] == 0);
$showLinkRow = !$this->config['LINKOFF'];
$showHelp = ($this->config['BBSMODE_ADMINONLY'] != 1);
```

**改善後**:
```php
'visibility' => [
    'formConfig' => $this->config['BBSMODE_ADMINONLY'] == 0,
    'linkRow' => !$this->config['LINKOFF'],
    'help' => $this->config['BBSMODE_ADMINONLY'] != 1,
    'undo' => $this->config['ALLOW_UNDO'],
    'siCheck' => isset($this->config['SHOWIMG']),
],
```

## 結論

**現時点での推奨**: 案1（メソッド分割）を次回実施。

翻訳文字列の問題は大きいが、テンプレート全体の修正が必要なため、別タスクとして計画する。
