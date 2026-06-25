<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Media Queue Connection (مهام الترميز الطويلة)
    |--------------------------------------------------------------------------
    |
    | اتصال مخصّص لمهام الوسائط الثقيلة (ترميز الفيديو/توليد المشتقّات). يجب أن
    | يكون retry_after فيه أكبر من أطول مهلة مهمة (TranscodeVideoAssetJob::timeout
    | = 2400ث) لمنع إعادة الإتاحة المزدوجة أثناء الترميز الطويل.
    |
    | آمن افتراضياً: على Redis في الإنتاج يُوجَّه تلقائياً إلى redis-media؛ وفي
    | الاختبارات (sync) يبقى null فتعمل المهام تزامنياً. يُتجاوَز عبر
    | MEDIA_QUEUE_CONNECTION عند الحاجة.
    |
    */

    'media_connection' => env(
        'MEDIA_QUEUE_CONNECTION',
        env('QUEUE_CONNECTION', 'database') === 'redis' ? 'redis-media' : null,
    ),

    /*
    |--------------------------------------------------------------------------
    | Queue Naming Convention (توثيق فقط — لا يغيّر السلوك)
    |--------------------------------------------------------------------------
    |
    | الأعمال غير المتزامنة تُوجَّه لطوابير منطقية مسمّاة عبر
    | ->onQueue('<name>'). الاصطلاح المعتمد:
    |
    |   default        → أعمال عامة خفيفة
    |   notifications  → إشعارات Firebase / push
    |   mail           → البريد (إعادة تعيين كلمة المرور ...)
    |   media          → تحويل/تحسين الصور (Spatie MediaLibrary)
    |   search         → فهرسة Meilisearch (Scout)
    |   sitemap        → توليد خريطة الموقع
    |   ai             → توليد محتوى الذكاء الاصطناعي
    |   analytics      → أحداث التحليلات
    |
    | التفاصيل والحدود في: .ai/performance-architecture.md
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        // اتصال الوسائط الثقيلة: retry_after مرتفع (> أطول مهلة ترميز = 2400ث) كي
        // لا يُعاد إتاحة مهمة الترميز الطويلة فتُنفَّذ مرّتين. يُشغَّله عامل طابور
        // media منفصل (انظر استراتيجية العمّال في وثائق النشر).
        'redis-media' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_MEDIA_QUEUE_RETRY_AFTER', 3600),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
