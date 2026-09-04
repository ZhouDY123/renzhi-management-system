---
version: alpha
colors:
  ocean-950: "#081A31"
  ocean-900: "#0D2442"
  ocean-800: "#16345A"
  cobalt-600: "#2855C7"
  jade-600: "#147A64"
  amber-600: "#A96516"
  danger-600: "#B4473E"
  canvas: "#F3F6FA"
  paper: "#FFFFFF"
  ink: "#152238"
  muted: "#66748A"
  line: "#DCE3ED"
typography:
  display:
    fontFamily: '"STSong", "Songti SC", "SimSun", serif'
    fontSize: "2rem"
    lineHeight: "1.25"
  body:
    fontFamily: '"Microsoft YaHei UI", "PingFang SC", "Noto Sans CJK SC", sans-serif'
    fontSize: "1rem"
    lineHeight: "1.65"
  data:
    fontFamily: '"Bahnschrift", "DIN Alternate", "Consolas", monospace'
    fontSize: "0.875rem"
    lineHeight: "1.45"
rounded:
  control: "10px"
  panel: "16px"
  modal: "20px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button:
    minHeight: "44px"
    radius: "10px"
  input:
    minHeight: "46px"
    radius: "10px"
  table:
    rowMinHeight: "54px"
  modal:
    radius: "20px"
---

## Overview

任职管理系统面向 HR、管理者和面试官，核心任务是对高风险的人才决策进行清晰、可核验、可追溯的记录。视觉北极星是“管理者桌面的决策档案”：理性、安静、层级明确，而不是通用 SaaS 模板或装饰性数据大屏。

界面的标志性元素是“档案索引线”：页面标题和关键分区使用深海蓝竖线与短标签建立文档索引感。其余界面保持克制，不叠加无意义渐变、玻璃拟态和过多阴影。

运行时 CSS 变量是代码侧唯一来源，本文件镜像已接受的系统值并解释用途；映射位置为 `public/assets/extra.css` 的最终令牌区。

## Colors

深海蓝代表组织可信度与导航层级；钴蓝只用于主操作和焦点；青绿、琥珀、危险红只表达业务语义。画布使用冷灰蓝，内容使用白纸面，避免纯灰导致的低对比。

## Typography

正文优先使用清晰的中文无衬线字体。宋体仅用于一级页面标题和关键数字，形成“正式档案”气质，不进入长段正文。编号、分数、日期和代码使用数据字体以提高纵向扫描效率。

正文最小 14px，后台常规正文 15–16px；表单和主要操作不得缩小到 14px 以下。中文正文行高保持 1.55–1.7。

## Layout

桌面端使用 264px 固定侧栏、最大 1440px 内容区和 24px 基础区块间距。主页面由标题区、工具区、内容面板组成。表格在自身容器内横向滚动，不锁死整个页面。720px 以下进入紧凑侧栏，560px 以下表单弹窗转为全屏工作面板。

## Elevation & Depth

静态面板以边框为主，阴影仅用于悬浮弹窗、当前任务卡和主操作，不用阴影堆叠制造层级。画布、纸面、浮层三层足够覆盖系统。

## Shapes

控件采用 10px 圆角，面板 16px，弹窗 20px；状态徽标可使用胶囊形。避免所有元素都变成胶囊或完全无圆角。

## Components

按钮按“强调程度 × 语义意图”组合。主按钮使用钴蓝实底；普通操作使用描边或文字按钮；停用、重置等危险操作使用红色文字，最终确认才使用红色实底。

表单字段至少 46px 高，标签始终可见。弹窗标题、可滚动正文和底部操作保持稳定。表格标题吸顶，行高舒适，状态以中文文字和颜色共同表达。

## Do's and Don'ts

- 使用真实业务名称和明确动词，例如“保存岗位”“轮换二维码”。
- 用留白、分隔线和字号建立层级。
- 使用统一弹窗、确认框、反馈和空状态。
- 不使用浏览器原生 `alert`、`confirm`、`prompt`。
- 不以英文状态词、极小字号或纯颜色传达关键信息。
- 不为装饰堆叠渐变、发光、悬浮卡片和无业务含义的图标。
