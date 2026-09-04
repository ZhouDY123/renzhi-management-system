# 任职管理系统开发文档（PHP + SQLite）

> 版本：V1.4　|　配套需求文档：任职管理系统需求说明书 V1.3　|　日期：2026-09-03
> 技术栈：PHP 8.1+ / SQLite 3（PDO_SQLITE，WAL 模式）/ 管理端 PC Web + 应聘端·面试端 H5

> V1.4 关键约束：一个手机号（代表一个自然人）全系统只能应聘一个岗位；面试官扫码后必须填写本人姓名，并与该场次预设面试官名单匹配。评分状态、审核状态分离，历史题目和评分标准按版本留存。

---

## 一、系统概述与技术选型

### 1.1 系统定位
面向集团及子公司的人才招聘甄选，实现「岗位标准数字化 → HR预登记应聘者 → 应聘扫码验证答题 → 人才审核入库 → 面试扫码评分 → 终审录用」全流程线上化。系统由三端组成：

| 端 | 使用者 | 形态 | 入口 |
|---|---|---|---|
| 管理端 | 系统管理员 / 分管领导 / HR 审核员 | PC Web | 后台登录 |
| 应聘端 | 应聘者 | 手机 H5 | 扫描应聘二维码 |
| 面试评分端 | 面试官 | 手机 H5 | 扫描岗位面试二维码 |

### 1.2 技术选型
- **后端**：PHP 8.1+，使用 PDO（`PDO_SQLITE`）操作 SQLite，自研轻量路由（或选用 Laravel/Lumen，本文以自研轻量路由为例，便于直接部署）。
- **数据库**：SQLite 3，单文件 `data/app.db`，开启 WAL 模式以支持并发读与单写。
- **前端**：管理端 PC Web（服务端渲染 + 少量 JS）；应聘端/面试端为响应式 H5，适配微信内置浏览器。
- **二维码**：服务端生成，使用 `phpqrcode` 或 `endroid/qr-code` 库，内容为带 token 的 H5 URL。
- **部署**：Nginx + PHP-FPM（单机即可），SQLite 文件 + 备份脚本。

### 1.3 选型理由
- 单文件数据库、零独立数据库服务、部署成本低，适合集团内部中小规模招聘场景；
- PHP 生态成熟、主机便宜、易交付；
- WAL 模式下读不阻塞写，适合「扫码高峰写答卷 + 后台读审核」的读写混合负载。

---

## 二、总体架构与目录结构

### 2.1 部署架构
```
                         ┌───────────────┐
   手机扫码(应聘/面试)  │   Nginx :80/443 │
        ───────────────▶│  静态资源 + 反代 │
                         └───────┬───────┘
                                 │
                         ┌───────▼───────┐
                         │   PHP-FPM     │
                         │  (app 代码)   │
                         └───────┬───────┘
                                 │  PDO
                         ┌───────▼───────┐
                         │  SQLite (WAL) │
                         │  data/app.db  │
                         └───────────────┘
```

### 2.2 目录结构
```
zhi-ren-system/
├── public/                      # Web 根目录（Nginx 指向此）
│   ├── index.php                # 路由入口（管理端 API + 页面）
│   ├── h5.php                   # 应聘端/面试端 H5 路由入口
│   └── assets/                  # 静态资源(css/js/img)
├── app/
│   ├── bootstrap.php            # 公共引导：autoload、DB 连接、时区
│   ├── db.php                   # DB 连接与迁移
│   ├── router.php               # 轻量路由器
│   ├── controllers/
│   │   ├── AdminPostController.php       # 岗位/综合测评评分标准/基本素质
│   │   ├── AdminQuestionController.php  # 基本题库 + 岗位专业题
│   │   ├── AdminPreRegisterController.php # 应聘者预登记（按岗位录入名单）
│   │   ├── AdminTalentController.php   # 人才审核/人才库
│   │   ├── AdminInterviewController.php# 面试安排/结果
│   │   ├── H5ApplyController.php        # 应聘端：扫码/手机号验证/答题/提交
│   │   └── H5InterviewController.php   # 面试端：扫码/身份/评分
│   ├── services/
│   │   ├── ScoreService.php             # 答题计分（综合测评自动评分+基本素质+专业题）
│   │   ├── PreRegisterService.php       # 预登记/手机号匹配岗位
│   │   ├── EvalScoreService.php         # 综合测评评分标准自动计分
│   │   ├── InterviewScoreService.php   # 面试评分汇总
│   │   ├── QrCodeService.php            # 二维码生成
│   │   └── TokenService.php             # 短 token / 防重
│   └── models/                          # 简单表数据访问层（可省）
├── config/
│   └── app.php                  # 权重、时限、字典等配置
├── data/
│   ├── app.db                   # SQLite 主库
│   ├── app.db-wal               # WAL（自动）
│   └── backups/                 # 定时备份
├── migrations/
│   └── 001_init.sql             # 建库脚本
└── README.md
```

---

## 三、数据库设计（SQLite DDL）

> 所有表使用 `INTEGER PRIMARY KEY` 自增；时间用 `TEXT`（ISO8601）或 `INTEGER`（Unix 秒），本文统一用 `TEXT`。布尔用 `INTEGER`（0/1）。外键需 `PRAGMA foreign_keys=ON`。

### 3.1 用户与权限
```sql
-- 用户（含管理员、HR、面试官等内部人员）
CREATE TABLE user (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,                 -- password_hash(PASSWORD_BCRYPT)
    real_name     TEXT NOT NULL,
    mobile        TEXT,
    role          TEXT NOT NULL,                  -- admin / leader / hr / interviewer
    status        INTEGER NOT NULL DEFAULT 1,     -- 1启用 0停用
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 操作日志
CREATE TABLE op_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER,
    action     TEXT NOT NULL,                      -- 如 interview_score_reset
    target     TEXT,                               -- 如 session_id:c123
    detail     TEXT,                               -- JSON 摘要
    ip         TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
```

### 3.2 岗位与标准
```sql
-- 岗位
CREATE TABLE post (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,                     -- 岗位名称，如 GEO 岗位
    company     TEXT,                               -- 公司/部门
    series      TEXT,                               -- 岗位序列
    status      TEXT NOT NULL DEFAULT 'recruiting',-- recruiting/reserve/stop
    duty        TEXT,                               -- 岗位职责简述
    q_apply_token TEXT,                             -- 应聘二维码 token
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX uk_post_apply_token ON post(q_apply_token);

-- 综合测评评分标准（系统级统一维护，全岗位共用，4 大类 15 项维度）
CREATE TABLE scoring_standard (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    category    TEXT NOT NULL,                     -- basic_info/exp_qualification/work_history/skills
    dim_code    TEXT NOT NULL,                     -- age/health/edu/school/major/title/politics/work_years/prof_years/group_co/listed_co/private_co/work_bg/computer/language
    dim_name    TEXT NOT NULL,                     -- 维度名称
    tier_label  TEXT NOT NULL,                     -- 分档标准描述
    match_type  TEXT NOT NULL DEFAULT 'eq',         -- eq/range/in/bool_range
    match_rule  TEXT NOT NULL DEFAULT '{}',         -- JSON结构化规则，禁止依赖tier_label文本解析
    tier_value  REAL NOT NULL,                     -- 分值
    version     INTEGER NOT NULL DEFAULT 1,         -- 评分方案版本
    status      TEXT NOT NULL DEFAULT 'draft',      -- draft/published/retired；published不可原地修改
    sort        INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_ss_dim ON scoring_standard(dim_code);

-- 综合测评得分明细（答卷提交时系统自动计算并写入，供审核查看）
CREATE TABLE eval_score_detail (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    answer_id   INTEGER NOT NULL,
    dim_code    TEXT NOT NULL,
    dim_name    TEXT NOT NULL,
    matched_tier TEXT,                             -- 匹配的分档标准
    score       REAL NOT NULL,                     -- 该维度得分
    FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE
);
CREATE INDEX idx_esd_answer ON eval_score_detail(answer_id);

-- 基本素质（每岗位8项）
CREATE TABLE suzhi_item (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL,
    dim_code   TEXT NOT NULL,                      -- pinzhi/strategy/...
    dim_name   TEXT NOT NULL,
    point_desc TEXT,                               -- 评价要点
    weight     REAL NOT NULL DEFAULT 0,
    sort       INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uk_suzhi_post_dim ON suzhi_item(post_id, dim_code);
```

### 3.3 题库（基本题 + 岗位专业题）
```sql
-- 基本题库（通用题，全员共用）
CREATE TABLE question_base (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    group_type TEXT NOT NULL,                      -- eval(综合测评评分)/suzhi(基本素质测评)
    dim_code   TEXT,                                -- 关联维度，可空
    q_type     TEXT NOT NULL,                      -- single/multi/short
    stem       TEXT NOT NULL,                      -- 题干
    options    TEXT,                                -- JSON：["A..","B.."]
    answer     TEXT,                                -- 答案/评分标准
    score      REAL NOT NULL DEFAULT 0,
    status     INTEGER NOT NULL DEFAULT 1,         -- 1启用 0停用
    sort       INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 岗位专业题库（按岗位配置）
CREATE TABLE question_post (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL,
    q_type     TEXT NOT NULL,                      -- single/multi/short
    stem       TEXT NOT NULL,
    options    TEXT,                                -- JSON
    answer     TEXT,                                -- 参考答案
    point_desc TEXT,                                -- 评分要点（面试评分用）
    score      REAL NOT NULL DEFAULT 0,
    status     INTEGER NOT NULL DEFAULT 1,
    sort       INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE
);

-- 岗位题卷版本；published后不可原地修改，只能复制生成下一版本
CREATE TABLE question_set (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id     INTEGER NOT NULL,
    version     INTEGER NOT NULL,
    status      TEXT NOT NULL DEFAULT 'draft',      -- draft/published/retired
    published_at TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE,
    UNIQUE(post_id, version)
);

CREATE TABLE question_set_item (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    question_set_id INTEGER NOT NULL,
    question_type   TEXT NOT NULL,                  -- base/post
    question_id     INTEGER NOT NULL,
    sort            INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(question_set_id) REFERENCES question_set(id) ON DELETE CASCADE,
    UNIQUE(question_set_id, question_type, question_id)
);
```

### 3.4 应聘者预登记与答卷
```sql
-- 候选人预登记（HR 按岗位提前录入姓名+手机号，扫码后凭手机号匹配岗位）
CREATE TABLE candidate_pre_register (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL,                     -- 绑定岗位
    name       TEXT NOT NULL,                        -- 应聘者姓名
    mobile     TEXT NOT NULL,                        -- 手机号
    status     TEXT NOT NULL DEFAULT 'registered',   -- registered/verified/answering/answered/canceled
    created_by INTEGER,                              -- 登记人（HR user.id）
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE
);
-- 业务硬约束：一个人只能应聘一个岗位。手机号全局唯一，取消后如需改岗由HR修改原登记并留痕，不能新建第二条。
CREATE UNIQUE INDEX uk_pre_mobile ON candidate_pre_register(mobile);

-- 应聘者（答卷提交时自动创建或从预登记带入）
CREATE TABLE candidate (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT NOT NULL,
    gender           TEXT,
    birth_date       TEXT,                          -- 出生年月（用于计算年龄分档）
    health           TEXT,                           -- 健康状况
    edu              TEXT,                           -- 学历
    school_tier      TEXT,                           -- 院校层次
    major            TEXT,                           -- 专业
    title            TEXT,                           -- 职称
    politics         TEXT,                           -- 政治面貌
    work_years       INTEGER,                        -- 参加工作年限
    prof_years       INTEGER,                        -- 从事专业年限
    group_co_years   INTEGER,                        -- 集团公司工作年限
    group_co_mgmt    INTEGER DEFAULT 0,              -- 集团公司是否高管 0/1
    listed_co_years  INTEGER,                        -- 上市公司工作年限
    listed_co_mgmt   INTEGER DEFAULT 0,              -- 上市公司是否高管 0/1
    private_co_years INTEGER,                         -- 非上市公司工作年限
    private_co_mgmt  INTEGER DEFAULT 0,               -- 非上市公司是否高管 0/1
    work_bg          TEXT,                            -- 工作背景（行业企业类型）
    computer_skill   TEXT,                            -- 计算机操作能力
    language         TEXT,                            -- 外语能力
    mobile           TEXT NOT NULL,
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX uk_candidate_mobile ON candidate(mobile);

-- 答卷（一个人全系统限投1次）
CREATE TABLE answer (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    candidate_id INTEGER NOT NULL,
    post_id      INTEGER NOT NULL,
    candidate_snapshot TEXT NOT NULL,              -- JSON：提交时个人资料快照
    post_snapshot TEXT NOT NULL,                   -- JSON：提交时岗位名称/公司/要求快照
    question_set_id INTEGER NOT NULL,               -- 本次使用的已发布题卷版本
    idempotency_key TEXT NOT NULL UNIQUE,           -- 客户端每次逻辑提交生成并在重试时复用
    submit_at    TEXT NOT NULL DEFAULT (datetime('now')),
    ip           TEXT,
    ua           TEXT,
    FOREIGN KEY(candidate_id) REFERENCES candidate(id),
    FOREIGN KEY(post_id) REFERENCES post(id),
    FOREIGN KEY(question_set_id) REFERENCES question_set(id)
);
CREATE UNIQUE INDEX uk_answer_candidate ON answer(candidate_id);

-- 答题明细（基本题 + 岗位专业题）
CREATE TABLE answer_detail (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    answer_id  INTEGER NOT NULL,
    q_type     TEXT NOT NULL,                       -- base/post
    question_id INTEGER NOT NULL,                   -- 对应 question_base.id 或 question_post.id
    answer_val TEXT,                                -- 单选选项或多选JSON或简答文本
    score      REAL NOT NULL DEFAULT 0,
    score_status TEXT NOT NULL DEFAULT 'auto',      -- auto/manual_pending/manual_done
    stem_snapshot TEXT NOT NULL,                    -- 提交时题干快照
    options_snapshot TEXT,                          -- 提交时选项JSON快照
    answer_snapshot TEXT,                           -- 当时答案/评分标准快照（仅后台可见）
    max_score_snapshot REAL NOT NULL DEFAULT 0,     -- 当时题目满分快照
    FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE
);

-- 简答题等人工评分记录；更正采用追加记录，保留审计链
CREATE TABLE answer_manual_score (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    answer_detail_id INTEGER NOT NULL,
    user_id          INTEGER NOT NULL,
    score            REAL NOT NULL,
    note             TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(answer_detail_id) REFERENCES answer_detail(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES user(id)
);

-- 测评结果（自动计分）
CREATE TABLE result (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    answer_id       INTEGER NOT NULL UNIQUE,
    eval_score    REAL NOT NULL DEFAULT 0,         -- 综合测评得分（百分制）
    suzhi_score     REAL NOT NULL DEFAULT 0,         -- 基本素质得分
    postq_score     REAL NOT NULL DEFAULT 0,         -- 岗位专业题得分
    total_score     REAL NOT NULL DEFAULT 0,         -- 综合分
    standard_version INTEGER NOT NULL,               -- 本次计算采用的综合测评标准版本
    weight_snapshot TEXT NOT NULL,                   -- JSON：本次计算采用的各项权重
    score_status    TEXT NOT NULL DEFAULT 'waiting', -- waiting/auto_scored/manual_pending/completed
    review_status   TEXT NOT NULL DEFAULT 'pending', -- pending/first_pass/first_reject/interview/final_pass/final_reject/talent_pool
    FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE
);

-- 审核记录
CREATE TABLE review (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    result_id  INTEGER NOT NULL,
    user_id    INTEGER NOT NULL,
    opinion    TEXT,                                -- pass(推荐复试)/reject
    note       TEXT,
    stage      TEXT NOT NULL,                       -- first(初审)/final(终审)
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(result_id) REFERENCES result(id)
);
```

### 3.5 面试管理（多面试官评分）
```sql
-- 面试场次（一岗一天一场）
CREATE TABLE interview_session (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id     INTEGER NOT NULL,
    interview_date TEXT NOT NULL,                   -- 面试日期 YYYY-MM-DD
    time_range  TEXT,                                -- 时间段
    location    TEXT,
    status      TEXT NOT NULL DEFAULT 'pending',    -- pending/scoring/done/canceled
    qr_token    TEXT NOT NULL UNIQUE,               -- 面试二维码 token
    created_by  INTEGER,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(post_id) REFERENCES post(id)
);
CREATE UNIQUE INDEX uk_session_post_date ON interview_session(post_id, interview_date);

-- 场次面试人员（已通过初审的候选人）
CREATE TABLE interview_candidate (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id   INTEGER NOT NULL,
    candidate_id INTEGER NOT NULL,
    answer_id    INTEGER NOT NULL,                  -- 关联答卷/结果
    seq          INTEGER NOT NULL DEFAULT 0,        -- 面试顺序
    status       TEXT NOT NULL DEFAULT 'pending',   -- pending/scored
    FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uk_session_candidate ON interview_candidate(session_id, candidate_id);

-- 场次面试官（一场多名）
CREATE TABLE session_interviewer (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id   INTEGER NOT NULL,
    user_id      INTEGER NOT NULL,                  -- 对应 user.id
    weight       REAL NOT NULL DEFAULT 1,           -- 评分权重
    is_lead      INTEGER NOT NULL DEFAULT 0,        -- 是否主面试官
    confirmed    INTEGER NOT NULL DEFAULT 0,        -- 是否已扫码确认身份
    FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uk_session_interviewer ON session_interviewer(session_id, user_id);

-- 面试评分明细（每名面试官对每名候选人：维度级得分）
CREATE TABLE interview_score (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id   INTEGER NOT NULL,
    interviewer_id INTEGER NOT NULL,                -- session_interviewer.id
    candidate_id INTEGER NOT NULL,                  -- interview_candidate.id
    group_type   TEXT NOT NULL,                     -- suzhi(基本素质)/postq(岗位专业题)
    dim_code     TEXT NOT NULL,                     -- 维度/题目 code
    score        REAL NOT NULL DEFAULT 0,           -- 统一存0~100；前端可显示1~5，但提交前须归一化
    FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uk_interview_dim_score
ON interview_score(interviewer_id, candidate_id, group_type, dim_code);

-- 面试评语（每人对每候选人一条）
CREATE TABLE interview_comment (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id     INTEGER NOT NULL,
    interviewer_id INTEGER NOT NULL,
    candidate_id   INTEGER NOT NULL,
    comment        TEXT,
    submitted_at   TEXT,
    FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE
);
CREATE UNIQUE INDEX uk_icv ON interview_comment(session_id, interviewer_id, candidate_id);
```

### 3.6 字典
```sql
CREATE TABLE dict (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    type  TEXT NOT NULL,        -- edu/school_tier/title/politics/language/health/work_bg/computer ...
    code  TEXT NOT NULL,
    label TEXT NOT NULL,
    sort  INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX uk_dict_type_code ON dict(type, code);
```

### 3.7 初始化与索引
```sql
PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;
CREATE INDEX idx_answer_post ON answer(post_id);
CREATE INDEX idx_result_score_status ON result(score_status);
CREATE INDEX idx_result_review_status ON result(review_status);
CREATE INDEX idx_isess_post ON interview_session(post_id);
CREATE INDEX idx_iscore_cand ON interview_score(candidate_id);
CREATE INDEX idx_iscore_intv ON interview_score(interviewer_id);
```

> 索引只在其首次出现处创建，迁移文件不得重复使用同名 `CREATE INDEX`。生产迁移建议统一使用版本化迁移，不重复执行整份 DDL。

---

## 四、核心业务逻辑

### 4.0 应聘者预登记与扫码身份匹配（PreRegisterService）

应聘者扫码后，系统通过手机号在 `candidate_pre_register` 表中匹配，自动定位到 HR 预登记时绑定的岗位，直接加载该岗位题卷。

```php
// app/services/PreRegisterService.php
class PreRegisterService {
    /**
     * 扫码后手机号验证 -> 匹配预登记岗位
     * @return array {matched:bool, post_id?:int, name?:string, pre_reg_id?:int, msg?:string}
     */
    public function matchByMobile(string $mobile, string $applyToken): array {
        $pdo = db();

        // 1. 校验 apply_token 对应岗位是否招聘中
        $post = $pdo->prepare("SELECT id, name, status FROM post WHERE q_apply_token=?");
        $post->execute([$applyToken]);
        $postRow = $post->fetch(PDO::FETCH_ASSOC);
        if (!$postRow || $postRow['status'] !== 'recruiting') {
            return ['matched'=>false, 'msg'=>'该岗位已停止招聘'];
        }

        // 2. 手机号必须与二维码所指岗位的预登记记录一致，禁止串岗
        $stmt = $pdo->prepare("
            SELECT id, post_id, name, status
            FROM candidate_pre_register
            WHERE mobile=? AND post_id=? AND status!='canceled'
            LIMIT 1");
        $stmt->execute([$mobile, $postRow['id']]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            return ['matched'=>false, 'msg'=>'未找到您的应聘登记信息，请联系人力资源部门'];
        }

        // 3. 校验是否已答卷（防重复）
        if ($reg['status'] === 'answered') {
            return ['matched'=>false, 'msg'=>'您已完成该岗位的答题，无需重复提交'];
        }

        // 4. 匹配成功：原子更新为verified，并签发短期答题token；token绑定pre_reg_id/post_id/mobile/有效期
        $pdo->prepare("UPDATE candidate_pre_register SET status='verified' WHERE id=? AND status='registered'")
            ->execute([$reg['id']]);
        return [
            'matched'    => true,
            'pre_reg_id'=> $reg['id'],
            'post_id'   => $reg['post_id'],
            'name'      => $reg['name'],
            'post_name' => $postRow['name'],
            'apply_session_token' => (new TokenService())->issueApplySession(
                (int)$reg['id'], (int)$reg['post_id'], $mobile, 1800
            ),
        ];
    }

    /**
     * 答卷提交后更新预登记状态
     */
    public function markAnswered(int $preRegId): void {
        $pdo = db();
        $pdo->prepare("UPDATE candidate_pre_register SET status='answered' WHERE id=?")
            ->execute([$preRegId]);
    }
}
```

预登记规则：

- 手机号在 `candidate_pre_register` 中全局唯一，一个人只能应聘一个岗位。
- 如候选人取消后需要改岗，由 HR 在原预登记记录上执行“改岗”，校验其尚未提交答卷后更新 `post_id`，并写入 `op_log`；不得新增第二条预登记。
- `/h5/post/{id}` 和 `/h5/apply/submit` 必须验证 `apply_session_token`，岗位、手机号和预登记编号一律取服务端 token 绑定值，不信任客户端传值。
- `registered → verified → answering → answered` 的状态变化使用条件更新；答卷、明细、结果和 `answered` 状态必须在同一数据库事务内提交。

### 4.1 综合测评评分标准自动计分（EvalScoreService）

系统根据应聘者填报的基本信息，以已发布版本的 `match_type + match_rule` 结构化规则进行匹配，计算各项维度得分并汇总。该版本满分由各维度最高分动态求和，不写死 47 分；`tier_label` 只用于展示，不参与程序判断。答卷一旦提交，采用的标准版本写入 `result.standard_version`，已发布版本不可原地修改。

```php
// app/services/EvalScoreService.php
class EvalScoreService {
    public function __construct(private int $standardVersion) {}

    /**
     * 根据应聘者信息自动计算综合测评得分
     * @return array {raw_score:float, pct_score:float, details:array}
     */
    public function calcEvalScore(int $candidateId): array {
        $pdo = db();
        $cand = $pdo->prepare("SELECT * FROM candidate WHERE id=?");
        $cand->execute([$candidateId]);
        $c = $cand->fetch(PDO::FETCH_ASSOC);
        if (!$c) return ['raw_score'=>0, 'pct_score'=>0, 'details'=>[]];

        $details = [];
        $totalRaw = 0;

        // 逐维度匹配评分标准
        $dimMatch = $this->matchAge($c);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('health', $c['health'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('edu', $c['edu'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('school', $c['school_tier'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('major', $c['major'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('title', $c['title'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('politics', $c['politics'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchWorkYears($c['work_years'] ?? 0);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchProfYears($c['prof_years'] ?? 0);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchGroupCo($c['group_co_years'] ?? 0, $c['group_co_mgmt'] ?? 0);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchListedCo($c['listed_co_years'] ?? 0, $c['listed_co_mgmt'] ?? 0);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchPrivateCo($c['private_co_years'] ?? 0, $c['private_co_mgmt'] ?? 0);
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('work_bg', $c['work_bg'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('computer', $c['computer_skill'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $dimMatch = $this->matchByDimCode('language', $c['language'] ?? '');
        $totalRaw += $dimMatch['score']; $details[] = $dimMatch;

        $maxScore = $this->getVersionMaxScore();
        $pctScore = $maxScore > 0 ? round(min(100, $totalRaw / $maxScore * 100), 2) : 0;

        return ['raw_score'=>$totalRaw, 'pct_score'=>$pctScore, 'details'=>$details];
    }

    /** 年龄分档匹配（需根据出生年月计算） */
    private function matchAge(array $c): array {
        $age = $c['birth_date']
            ? (new DateTimeImmutable($c['birth_date']))->diff(new DateTimeImmutable('today'))->y
            : null;
        $tiers = $this->getTiers('age');
        foreach ($tiers as $t) {
            if ($age !== null && $this->matchStructuredRule($age, $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'age','dim_name'=>'年龄','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'age','dim_name'=>'年龄','matched_tier'=>'未匹配','score'=>0];
    }

    /** 工作年限分档匹配 */
    private function matchWorkYears(int $years): array {
        $tiers = $this->getTiers('work_years');
        foreach ($tiers as $t) {
            if ($this->matchStructuredRule($years, $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'work_years','dim_name'=>'参加工作年限','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'work_years','dim_name'=>'参加工作年限','matched_tier'=>'未匹配','score'=>0];
    }

    /** 从事专业年限分档匹配 */
    private function matchProfYears(int $years): array {
        $tiers = $this->getTiers('prof_years');
        foreach ($tiers as $t) {
            if ($this->matchStructuredRule($years, $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'prof_years','dim_name'=>'从事专业年限','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'prof_years','dim_name'=>'从事专业年限','matched_tier'=>'未匹配','score'=>0];
    }

    /** 集团公司经历分档匹配 */
    private function matchGroupCo(int $years, int $isMgmt): array {
        $tiers = $this->getTiers('group_co');
        foreach ($tiers as $t) {
            if ($this->matchStructuredRule(['years'=>$years,'is_mgmt'=>$isMgmt], $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'group_co','dim_name'=>'集团公司','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'group_co','dim_name'=>'集团公司','matched_tier'=>'未匹配','score'=>0];
    }

    /** 上市公司经历分档匹配 */
    private function matchListedCo(int $years, int $isMgmt): array {
        $tiers = $this->getTiers('listed_co');
        foreach ($tiers as $t) {
            if ($this->matchStructuredRule(['years'=>$years,'is_mgmt'=>$isMgmt], $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'listed_co','dim_name'=>'上市公司','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'listed_co','dim_name'=>'上市公司','matched_tier'=>'未匹配','score'=>0];
    }

    /** 非上市公司经历分档匹配 */
    private function matchPrivateCo(int $years, int $isMgmt): array {
        $tiers = $this->getTiers('private_co');
        foreach ($tiers as $t) {
            if ($this->matchStructuredRule(['years'=>$years,'is_mgmt'=>$isMgmt], $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>'private_co','dim_name'=>'非上市公司','matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>'private_co','dim_name'=>'非上市公司','matched_tier'=>'未匹配','score'=>0];
    }

    /** 通用维度匹配：应聘端提交字典code，按结构化JSON规则匹配，禁止模糊包含 */
    private function matchByDimCode(string $dimCode, string $value): array {
        $tiers = $this->getTiers($dimCode);
        $dimName = $this->getDimName($dimCode);
        foreach ($tiers as $t) {
            if ($value !== '' && $this->matchStructuredRule($value, $t['match_type'], $t['match_rule'])) {
                return ['dim_code'=>$dimCode,'dim_name'=>$dimName,'matched_tier'=>$t['tier_label'],'score'=>$t['tier_value']];
            }
        }
        return ['dim_code'=>$dimCode,'dim_name'=>$dimName,'matched_tier'=>'未匹配','score'=>0];
    }

    private function getTiers(string $dimCode): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT tier_label, tier_value, match_type, match_rule
            FROM scoring_standard WHERE dim_code=? AND version=? AND status='published' ORDER BY sort");
        $stmt->execute([$dimCode, $this->standardVersion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getDimName(string $dimCode): string {
        $names = ['age'=>'年龄','health'=>'健康状况','edu'=>'学历','school'=>'毕业院校',
                   'major'=>'专业','title'=>'职称','politics'=>'政治面貌',
                   'work_years'=>'参加工作年限','prof_years'=>'从事专业年限',
                   'group_co'=>'集团公司','listed_co'=>'上市公司','private_co'=>'非上市公司',
                   'work_bg'=>'工作背景','computer'=>'计算机基本操作','language'=>'外语'];
        return $names[$dimCode] ?? $dimCode;
    }

    // 结构化规则统一解释器：range校验上下界，eq校验单值，in校验code集合，bool_range同时校验年限和管理身份。
    // 所有输入先做类型、枚举和边界校验；规则重叠或某维度无匹配档位时，发布评分版本失败。
    private function matchStructuredRule(mixed $value, string $type, string $ruleJson): bool { /* ... */ }

    // 对当前已发布版本的每个dim_code取最高tier_value后求和。
    private function getVersionMaxScore(): float { /* ... */ }

    /** 将得分明细写入 eval_score_detail */
    public function saveDetails(int $answerId, array $details): void {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO eval_score_detail
            (answer_id, dim_code, dim_name, matched_tier, score) VALUES (?,?,?,?,?)");
        foreach ($details as $d) {
            $stmt->execute([$answerId, $d['dim_code'], $d['dim_name'], $d['matched_tier'], $d['score']]);
        }
    }
}
```

### 4.2 答题计分（ScoreService）

组卷：取该岗位「基本题（综合测评评分自动计分 + 基本素质测评）+ 该岗位专业题（question_post）」。

```php
// app/services/ScoreService.php
class ScoreService {
    public function calcAnswer(int $answerId): array {
        $pdo = db();

        $standardVersion = $this->publishedStandardVersionFor($answerId);
        $w = $this->validatedWeightFor($answerId); // 三项均为0~1且合计必须为1

        // 1. 综合测评：根据应聘者填报信息自动匹配评分标准计分
        $evalService = new EvalScoreService($standardVersion);
        $eval = $evalService->calcEvalScore($this->getCandidateId($answerId));
        $evalScore = $eval['pct_score'];  // 百分制
        $evalService->saveDetails($answerId, $eval['details']);

        // 2. 基本素质：各题得分按权重折算（百分制）
        $suzhiScore = $this->calcSuzhi($answerId);

        // 3. 岗位专业题：客观题自动判分 + 简答题待人工
        $postq = $this->calcPostQuestion($answerId);
        $postqScore = $postq['score'];

        // 4. 有简答题待人工评分时不形成最终综合分，更不能自动判定审核通过
        $scoreStatus = $postq['manual_pending'] ? 'manual_pending' : 'completed';
        $total = $scoreStatus === 'completed'
            ? round($evalScore*$w['eval'] + $suzhiScore*$w['suzhi'] + $postqScore*$w['postq'], 2)
            : 0;

        $pdo->prepare("INSERT INTO result
            (answer_id, eval_score, suzhi_score, postq_score, total_score,
             standard_version, weight_snapshot, score_status, review_status)
            VALUES (?,?,?,?,?,?,?,?, 'pending')
            ON CONFLICT(answer_id) DO UPDATE SET
             eval_score=excluded.eval_score, suzhi_score=excluded.suzhi_score,
             postq_score=excluded.postq_score, total_score=excluded.total_score,
             standard_version=excluded.standard_version, weight_snapshot=excluded.weight_snapshot,
             score_status=excluded.score_status")
            ->execute([$answerId, $evalScore, $suzhiScore, $postqScore, $total,
                       $standardVersion, json_encode($w), $scoreStatus]);
        return compact('evalScore','suzhiScore','postqScore','total','scoreStatus');
    }
}
```

基本素质分先把每题换算为所属 `suzhi_item.dim_code` 的百分制维度分，再按 `suzhi_item.weight` 加权；发布岗位方案时须校验8项齐全、权重均为非负且合计为1。人工评分完成后重新调用同一计算入口，只有 `score_status=completed` 才允许进入初审。分数线只生成“建议通过/建议不通过”，最终结果由审核动作写入 `review_status`。

### 4.3 面试评分汇总（InterviewScoreService）

多面试官独立评分、加权平均：

```php
// app/services/InterviewScoreService.php
class InterviewScoreService {
    /**
     * 某候选人在某场次的维度加权均分
     * 维度得分 = Σ(该面试官维度得分 × 其权重) / Σ权重
     */
    public function candidateWeighted(int $sessionId, int $candidateId): array {
        $pdo = db();
        // 面试官权重表
        $rows = $pdo->prepare("
            SELECT si.id AS iv_id, si.weight, s.group_type, s.dim_code, s.score
            FROM interview_score s
            JOIN session_interviewer si ON si.id = s.interviewer_id
            WHERE s.session_id=? AND s.candidate_id=?");
        $rows->execute([$sessionId, $candidateId]);
        $items = $rows->fetchAll(PDO::FETCH_ASSOC);

        // 按维度聚合；每个维度使用实际提交该维度的面试官权重作分母
        $dim = []; $weightByDim = [];
        foreach ($items as $it) {
            $key = $it['group_type'].'::'.$it['dim_code'];
            $dim[$key] += $it['score'] * $it['weight'];
            $weightByDim[$key] += $it['weight'];
        }

        $result = [];
        foreach ($dim as $k => $v) {
            $result[$k] = round($v / ($weightByDim[$k] ?: 1), 2);
        }

        // 基本素质均分 / 专业题均分
        $suzhi = []; $postq = [];
        foreach ($result as $k=>$v) {
            [$g] = explode('::',$k);
            if ($g==='suzhi') $suzhi[]=$v;
            if ($g==='postq') $postq[]=$v;
        }
        $suzhiAvg = $this->weightedSuzhiByPostConfig($sessionId, $result); // 使用suzhi_item.weight
        $postqAvg = $postq ? round(array_sum($postq)/count($postq),2) : 0;

        // 面试综合分（默认各50%，可配）
        $w = config('app.interview_weight', ['suzhi'=>0.5,'postq'=>0.5]);
        $interviewScore = round($suzhiAvg*$w['suzhi'] + $postqAvg*$w['postq'], 2);

        // 场次完成判断：所有面试官均已为该候选人提交
        $allSubmitted = $this->allInterviewersSubmitted($sessionId, $candidateId);

        return [
            'dim_scores'     => $result,
            'suzhi_avg'      => $suzhiAvg,
            'postq_avg'      => $postqAvg,
            'interview_score'=> $interviewScore,
            'all_submitted'  => $allSubmitted,
        ];
    }

    private function allInterviewersSubmitted(int $sid, int $cid): bool {
        $pdo = db();
        $need = $pdo->prepare("SELECT COUNT(*) FROM session_interviewer WHERE session_id=?");
        $need->execute([$sid]);
        $total = (int)$need->fetchColumn();
        $done = $pdo->prepare("SELECT COUNT(DISTINCT interviewer_id)
            FROM interview_comment WHERE session_id=? AND candidate_id=? AND submitted_at IS NOT NULL");
        $done->execute([$sid,$cid]);
        return (int)$done->fetchColumn() >= $total;
    }
}
```

提交评分时必须一次性提交该候选人的全部必填维度，服务端逐项校验 `0 ≤ score ≤ 100`；缺项不能正式提交，只能保存草稿。代码中的 `candidateId` 指 `interview_candidate.id`，接口和数据库访问层统一命名为 `interview_candidate_id`，避免与 `candidate.id` 混淆。

场次状态机：`pending` → 任一面试官提交评分 → `scoring` → 全部候选人均完成 → `done`。

### 4.5 状态机与事务边界

- 预登记：`registered → verified → answering → answered`，任一未提交状态可由 HR 转为 `canceled`。
- 计分：`waiting → auto_scored → manual_pending → completed`；无人工题时可由 `auto_scored` 直接进入 `completed`。
- 审核：`pending → first_pass / first_reject → interview → final_pass / final_reject / talent_pool`。自动分数仅给出审核建议，不直接改变审核结论。
- 面试场次：`pending → scoring → done`，未开始或进行中的场次可以转为 `canceled`。
- 答卷提交必须在 `BEGIN IMMEDIATE` 事务中完成资格复核、候选人及答卷写入、题目快照、自动评分和预登记状态更新。使用客户端生成的幂等键处理网络重试；唯一约束冲突时返回已存在的提交结果，不重复写入。
- 人工评分完成、审核、评分重置、候选人改岗和二维码轮换均写入 `op_log`。状态更新必须带旧状态条件，防止并发越级流转。

### 4.4 二维码与防重

- 应聘码：`post.q_apply_token`，扫码进入 `https://host/h5.php?m=apply&t={token}`。
- 面试码：`interview_session.qr_token`，扫码进入 `https://host/h5.php?m=interview&t={token}`。
- token 为 `bin2hex(random_bytes(8))`，二维码内容即上述 URL。
- **应聘端验证流程**：扫码 → 输入手机号 → 按“手机号 + 二维码岗位”匹配唯一预登记 → 签发绑定岗位、手机号和预登记编号的短期 token → 加载题卷；匹配失败则拒绝进入答题。
- **防串岗**：应聘者只能作答预登记绑定的岗位题目，无法查看或作答其他岗位题目。
- **面试端验证流程**：扫码 → 输入本人姓名 → 在当前场次预设面试官中按姓名精确匹配启用用户 → 匹配唯一时签发绑定 `session_interviewer.id + session_id` 的短期评分 token。重名、未找到或停用账号均拒绝进入，并提示联系 HR；客户端后续不得自行传入或切换面试官身份。

---

## 五、接口设计

### 5.1 应聘端（H5）

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/h5/scan?t={apply_token}` | 扫码落地，返回岗位卡片 + 手机号验证入口 |
| POST | `/h5/verify` | 提交手机号验证身份，匹配预登记岗位，返回岗位+题卷信息 |
| GET | `/h5/post/{id}` | 凭短期答题 token 获取岗位摘要 + 已发布题卷快照 |
| POST | `/h5/apply/submit` | 凭短期答题 token 幂等提交；如有人工题则返回“待评分” |
| GET | `/h5/done/{answer_id}` | 提交成功页 |

手机号验证参数示例：
```json
{
  "apply_token": "a1b2c3d4e5f6a7b8",
  "mobile": "13800138000"
}
```

验证成功返回示例：
```json
{
  "matched": true,
  "pre_reg_id": 15,
  "apply_session_token": "服务端签发的短期token",
  "post": {"id": 12, "name": "GEO岗位", "company": "三奇发展集团"},
  "candidate": {"name": "张三", "mobile": "13800138000"},
  "questions_url": "/h5/post/12"
}
```

验证失败返回示例：
```json
{
  "matched": false,
  "msg": "未找到您的应聘登记信息，请联系人力资源部门"
}
```

提交参数示例：
```json
{
  "apply_session_token": "服务端签发的短期token",
  "candidate": {
    "name":"张三","mobile":"138...","gender":"男","birth_date":"1988-05",
    "health":"健康","edu":"本科","school_tier":"一本院校或国外同等级别院校",
    "major":"会计学","title":"中级","politics":"中共党员",
    "work_years":12,"prof_years":10,
    "group_co_years":5,"group_co_mgmt":1,
    "listed_co_years":3,"listed_co_mgmt":0,
    "private_co_years":4,"private_co_mgmt":0,
    "work_bg":"本行业知名企业","computer_skill":"Word/EXCEL/PPT/CAD熟练运用三种",
    "language":"掌握一门外语并且口语熟练"
  },
  "answers": [
    {"q_type":"suzhi","question_id":3,"answer_val":"4"},
    {"q_type":"post","question_id":7,"answer_val":"A,C"}
  ]
}
```
防重：`uk_pre_mobile` + `uk_answer_candidate` 唯一索引、预登记状态条件更新、提交幂等键共同保障。手机号、岗位和预登记编号以服务端 token 为准；客户端不能通过修改参数换岗。

### 5.2 面试评分端（H5）

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/h5/interview?t={qr_token}` | 扫码落地，只返回场次摘要和姓名填写入口，不公开面试官名单 |
| POST | `/h5/interview/login` | 填写本人姓名，与场次预设名单精确匹配后下发评分会话 token |
| GET | `/h5/interview/candidates` | 当日候选人列表 + 本人已评/未评状态 |
| GET | `/h5/interview/score-form?cid={candidate_id}` | 评分维度表（基本素质8项 + 岗位专业题维度） |
| POST | `/h5/interview/score` | 提交一人评分（各维度得分 + 评语） |
| POST | `/h5/interview/score/draft` | 暂存草稿（断点续评） |

评分提交参数示例：
```json
{
  "score_session_token": "服务端签发的短期token",
  "interview_candidate_id": 23,
  "scores": [
    {"group_type":"suzhi","dim_code":"pinzhi","score":5},
    {"group_type":"postq","dim_code":"q7","score":4}
  ],
  "comment": "专业基础扎实，沟通表达良好。"
}
```

面试官登录参数示例：
```json
{
  "qr_token": "场次二维码token",
  "real_name": "李四"
}
```

姓名须去除首尾空格后精确匹配 `user.real_name`，并同时校验用户启用、角色合法、已加入当前场次。如果同场存在重名，系统拒绝仅凭姓名登录，由 HR 先为人员配置唯一显示名或补充内部工号后再试。约束：同一 `interviewer_id × interview_candidate_id` 只允许一次正式提交；已提交者再次提交返回 409。重置需 HR 调管理端接口并写 `op_log`。

### 5.3 管理端（PC Web）

| 模块 | 方法 | 路径 | 说明 |
|---|---|---|---|
| 岗位 | GET/POST | `/admin/post` `/admin/post/{id}` | 岗位 CRUD + 启停 |
| 标准 | GET/POST | `/admin/scoring-standard` | 综合测评评分标准（4 大类 15 项维度分档与分值）系统级维护 |
| 标准 | GET/POST | `/admin/post/{id}/suzhi` | 基本素质 8 项配置 |
| 题库 | GET/POST | `/admin/question/base` `/admin/question/post` | 基本题库 / 岗位专业题库维护、组卷校验 |
| 预登记 | GET/POST | `/admin/pre-register` `/admin/pre-register/{id}` | 按岗位录入/批量导入应聘者名单（姓名+手机号）、查看登记状态与答题情况 |
| 预登记 | POST | `/admin/pre-register/import` | Excel 批量导入模板下载与上传 |
| 预登记 | GET | `/admin/pre-register/export?post_id={id}` | 导出某岗位预登记名单 |
| 二维码 | GET | `/admin/post/{id}/qrcode` | 下载应聘二维码 |
| 人才审核 | GET/POST | `/admin/talent/review` | 按岗位审核、通过/不推荐、终审 |
| 人才库 | GET | `/admin/talent/pool` | 检索与明细 |
| 面试安排 | GET/POST | `/admin/interview/session` | 创建场次、勾选人员、指定多名面试官与权重、生成面试码 |
| 面试安排 | POST | `/admin/interview/session/{id}/reset-score` | 重置某面试官某候选人评分（留痕） |
| 面试结果 | GET | `/admin/interview/result/{session_id}` | 各面试官明细 + 加权汇总 + 导出 |
| 系统 | GET/POST | `/admin/user` `/admin/dict` `/admin/log` | 用户/角色/字典/日志 |

---

## 六、页面清单

### 6.1 管理端
- 登录 / 首页（待办统计）
- 职位管理：岗位列表、综合测评评分标准（4 大类 15 项维度分档与分值维护）、基本素质评价、题库管理（基本题+岗位专业题）、应聘者预登记（按岗位录入/批量导入名单、查看登记与答题状态）
- 人才管理：人才审核、人才库、面试安排、面试结果
- 系统管理：用户与角色权限、字典与二维码配置、操作日志

### 6.2 应聘端 / 面试端（H5）
- 应聘端：扫码落地/手机号验证（输入手机号匹配预登记岗位）、在线答题（姓名+手机号自动带入+综合测评评分信息填报+基本素质测评+岗位专业题）、提交成功页
- 面试端：面试官扫码落地/填写姓名验证、面试候选人列表、逐人评分页（基本素质+岗位专业题逐项打分+评语）

---

## 七、安全与防作弊

1. **登录鉴权**：管理端 Session 登录；应聘端免登录但凭岗位二维码、手机号和短期答题 token 验证；面试端扫码后必须填写本人姓名，匹配当前场次预设名单后取得短期评分 token。
2. **应聘准入管控**：手机号全局只能存在一条预登记并只能应聘一个岗位。扫码后必须同时匹配手机号和二维码岗位；后续接口只使用服务端 token 中绑定的岗位信息，杜绝串岗答题。
3. **防重复提交**：
   - 应聘：`uk_pre_mobile` + `uk_answer_candidate` 唯一约束、预登记状态条件更新、客户端幂等键和服务端事务共同防重。
   - 面试评分：`interview_comment` 唯一索引 + 服务端状态校验（已评分返回 409）。
4. **评分不可自行篡改**：评分提交后状态锁定，修改须 HR 调专用重置接口，全部写入 `op_log`（谁、何时、重置了谁对谁的评分）。
5. **数据安全**：答卷含个人信息，传输走 HTTPS；手机号脱敏展示；导出需权限并留痕。
6. **防刷**：应聘端按 IP/手机号限频（如同一 IP 5 分钟内 ≤10 次验证）；二维码 token 定期轮换、停用岗位立即失效。面试端按场次 token、姓名和 IP 限频；姓名连续失败达到阈值后短时锁定。
7. **输入与会话安全**：所有状态修改启用 CSRF 防护；Session Cookie 使用 `HttpOnly + Secure + SameSite=Lax`；所有输出做 HTML 转义，上传文件校验类型和大小，SQL 仅使用参数化查询。

---

## 八、部署与运维

### 8.1 Nginx 配置示例
```nginx
server {
    listen 80;
    server_name hire.example.com;
    root /var/www/zhi-ren-system/public;

    location / { try_files $uri /index.php?$query_string; }
    location /h5 { try_files $uri /h5.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    location ~ /(data|app|config|migrations) { deny all; }   # 保护敏感目录
}
```

### 8.2 SQLite 注意事项
- 开启 WAL：`PRAGMA journal_mode=WAL;`（建库脚本已含）。
- 并发：WAL 下读不阻塞写，单写串行；招聘高峰写答卷量较大时仍可承受；如规模增长再迁 MySQL。
- 备份：定时 `sqlite3 data/app.db ".backup data/backups/app-$(date +%F).db"`（在线热备）。
- 权限：`data/` 目录需 PHP-FPM 可读写，禁止 Web 直接访问。

### 8.3 初始化
```bash
sqlite3 data/app.db < migrations/001_init.sql
# 创建超级管理员（交互式或脚本）
php app/cli/create-admin.php
```

---

## 九、开发里程碑

| 阶段 | 内容 | 产出 |
|---|---|---|
| M1 | 库表与基础框架、登录与权限 | 可登录后台骨架 |
| M2 | 岗位与标准（综合测评评分标准/基本素质/题库）、应聘者预登记、应聘二维码 | 可配置岗位、维护评分标准、按岗位预登记应聘者、生成应聘码 |
| M3 | 应聘端 H5（扫码、手机号验证、答题、计分、入库） | 可完成线上应聘 |
| M4 | 人才审核与人才库 | HR 可审核检索 |
| M5 | 面试安排、面试二维码、面试评分端、评分汇总 | 多面试官扫码评分闭环 |
| M6 | 终审、导出、日志、安全加固 | 全流程上线 |

---

## 十、与需求文档的对应关系

| 需求（V1.3） | 本文实现 |
|---|---|
| 3.1.2 人员综合测评评分标准（4 大类 15 项维度） | scoring_standard 表 + eval_score_detail 表 + EvalScoreService |
| 3.1.4 题库（基本题+岗位专业题） | question_base / question_post + 组卷接口 |
| 3.1.5 应聘者预登记 | candidate_pre_register 表 + AdminPreRegisterController + PreRegisterService |
| 3.2 人才审核 | result / review 表 + AdminTalentController |
| 3.4 应聘端扫码验证答题 | H5ApplyController（手机号验证+答题+综合测评自动计分） + PreRegisterService + EvalScoreService + ScoreService |
| 3.5 面试管理（多面试官扫码评分） | interview_session / candidate / interviewer / score + InterviewScoreService |
| 4 数据表结构 | 第三章 DDL 一一对应 |
| 5 页面清单 | 第六章页面清单 |

> 本文档为开发实现依据，字段命名与接口路径可在评审后微调；以需求说明书 V1.3 为功能基准。
