## 制作アプリのタイトル
Oshi Graphy （推しグラフィー）
## 制作アプリの説明
Oshi-Graphy（推しグラフィー）は、<br>
80〜90年代から今も活躍するアーティストの推し活を楽しむ中高年世代を対象にした<br>
推し活ダイアリー共有アプリです。<br>
ライブ参戦や日常の推し活を日記として残し、同じ世代の仲間と共有・交流できる場を提供します。
## 主な機能
- ダイアリー（公開／非公開、画像添付、推しアーティスト紐付け）
- コメント & いいね（ダイアリー／コメント）
- フォロー機能（ユーザー間の相互フォロー／フォロワー一覧）
- ブロック機能（ユーザー間の相互ブロック／ブロックユーザー一覧）
- ユーザープロフィール（アイコン画像・自己紹介）
- 通知（既読管理、ベルの未読数、通知リスト／詳細リンク）
- アーティスト管理（管理者のみ：CRUD）
- ダッシュボード（通知一覧、クイックリンク）
- AIアシスト（日記下書き補助）※Gemini API 連携
- 検索 UI（Select2 などの補助 UI、一部ページで利用）
- レスポンシブデザイン、ダークモード対応
## 技術スタック
- Backend : Laravel 12, PHP 8.4
- DB : MySQL 8
- Frontend : Tailwind CSS / Alpine.js / Vite / Blade Components
- Auth : Laravel Breeze（JP ローカライズ）
- AI / API連携 : Gemini API ( Google Generative AI)
- Environment :
  - Docker / Laravel Sail ( ローカル開発環境 )
  - さくらレンタルサーバ ( 本番環境 PHP 8.3 )

## デプロイ
GitHub Actionsにより、mainブランチへのpushをトリガーに、<br>
さくらレンタルサーバへSSH接続し、自動デプロイを実施。<br>
デプロイ時はLaravelのメンテナンスモードを使用し、<br>
依存関係の更新・マイグレーション・キャッシュクリアを自動化しています。

## ER Diagram
主要エンティティのリレーション構造

```mermaid
erDiagram
    USERS ||--o{ DIARIES : "has many"
    USERS ||--o{ COMMENTS : "has many"
    USERS ||--o{ LIKES : "has many"
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ FOLLOWS : "follower"
    USERS ||--o{ FOLLOWS : "followed"
    USERS ||--o{ BLOCKS : "blocker"
    USERS ||--o{ BLOCKS : "blocked"

    ARTISTS ||--o{ DIARIES : "has many"

    DIARIES ||--o{ DIARY_IMAGES : "has many"
    DIARIES ||--o{ COMMENTS : "has many"
    DIARIES ||--o{ LIKES : "morphMany (likeable)"
    COMMENTS ||--o{ LIKES : "morphMany (likeable)"
    COMMENTS ||--o{ COMMENTS : "replies (parent_id)"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string icon_path
        text   profile
        boolean is_admin
        datetime created_at
        datetime updated_at
    }

    FOLLOWS {
        bigint id PK
        bigint follower_id FK
        bigint followed_id FK
        datetime created_at
        datetime updated_at
    }

    BLOCKS {
        bigint id PK
        bigint blocker_id FK
        bigint blocked_id FK
        datetime created_at
        datetime updated_at
    }

    ARTISTS {
        bigint id PK
        string name
        string kana
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }

    DIARIES {
        bigint id PK
        bigint user_id FK
        bigint artist_id FK
        date   happened_on
        boolean is_public
        text   body
        datetime created_at
        datetime updated_at
    }

    DIARY_IMAGES {
        bigint id PK
        bigint diary_id FK
        string path
        datetime created_at
        datetime updated_at
    }

    COMMENTS {
        bigint id PK
        bigint diary_id FK
        bigint user_id FK
        text   body
        bigint parent_id
        tinyint depth
        string path 
        bigint root_id
        datetime created_at
        datetime updated_at
    }

    LIKES {
        bigint id PK
        bigint user_id FK
        string likeable_type
        bigint likeable_id
        datetime created_at
        datetime updated_at
    }

    NOTIFICATIONS {
        uuid id PK
        string type
        string notifiable_type
        bigint notifiable_id
        text data
        datetime read_at
        datetime created_at
        datetime updated_at
    }
```
