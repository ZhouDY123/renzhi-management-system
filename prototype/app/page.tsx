'use client';

import { useState } from 'react';
import { Bell, BriefcaseBusiness, CalendarDays, Check, ChevronDown, ChevronRight, CircleUserRound, ClipboardCheck, Clock3, FileText, LayoutDashboard, MapPin, MoreHorizontal, Phone, Plus, QrCode, Search, Settings, SlidersHorizontal, Users, UserRoundCheck } from 'lucide-react';

type View = 'dashboard'|'posts'|'talent'|'apply'|'interview';
type Theme = 'executive'|'jade'|'air';
const nav = [
  { id:'dashboard' as View,label:'工作台',icon:LayoutDashboard },{ id:'posts' as View,label:'职位管理',icon:BriefcaseBusiness },
  { id:'talent' as View,label:'人才审核',icon:ClipboardCheck,count:12 },{ id:'talent' as View,label:'人才库',icon:Users },
  { id:'interview' as View,label:'面试管理',icon:UserRoundCheck,count:3 },
];
const stats=[['招聘中岗位','08','本月新增 2 个','indigo'],['待审核人才','12','3 人即将超时','amber'],['本周面试','26','今日安排 6 人','cyan'],['人才库总量','286','较上月 +18','green']];
const candidates=[['陈晓宁','集团财务经理','84.6','待初审','amber'],['周雨欣','品牌运营总监','82.9','待初审','amber'],['林致远','信息化项目经理','88.2','待终审','indigo'],['王一帆','人力资源经理','79.5','待初审','amber']];
const posts=[['集团财务经理','集团总部 · 财务序列','招聘中','26','18','8'],['信息化项目经理','数字科技公司 · 技术序列','招聘中','18','12','5'],['品牌运营总监','品牌中心 · 管理序列','招聘中','15','9','4'],['人力资源经理','集团总部 · 职能序列','储备中','22','16','—']];

export default function Home(){
 const [view,setView]=useState<View>('dashboard');
 const [theme,setTheme]=useState<Theme>('executive');
 const mobile=view==='apply'||view==='interview';
 return <div className={`theme-root theme-${theme}`}>
  <div className="style-switch"><div><small>选择视觉风格</small><strong>{theme==='executive'?'A · 深海行政':theme==='jade'?'B · 玉石雅致':'C · 清透未来'}</strong></div>
   <button className={theme==='executive'?'picked':''} onClick={()=>setTheme('executive')}><i className="swatch executive"/><span>深海行政</span><em>稳重权威</em></button>
   <button className={theme==='jade'?'picked':''} onClick={()=>setTheme('jade')}><i className="swatch jade"/><span>玉石雅致</span><em>温润高级</em></button>
   <button className={theme==='air'?'picked':''} onClick={()=>setTheme('air')}><i className="swatch air"/><span>清透未来</span><em>轻盈现代</em></button>
  </div>
  <div className="prototype-switch"><span>页面预览</span>{[['dashboard','工作台'],['posts','岗位管理'],['talent','人才审核'],['apply','应聘端'],['interview','面试端']].map(([id,label])=><button key={id} onClick={()=>setView(id as View)} className={view===id?'on':''}>{label}</button>)}</div>
  {mobile?<MobilePreview view={view}/>:<AdminShell view={view} setView={setView}/>} 
 </div>;
}

function AdminShell({view,setView}:{view:View,setView:(v:View)=>void}){
 return <main className="admin-shell">
  <aside className="sidebar">
   <div className="brand"><span className="brand-mark">任</span><div><strong>任职管理</strong><small>RENZHI MANAGEMENT</small></div></div>
   <nav className="main-nav"><p>管理中心</p>{nav.map((n,i)=><button className={view===n.id&&(i!==3||view==='talent')?'nav-item active':'nav-item'} key={n.label} onClick={()=>setView(n.id)}><n.icon size={19}/><span>{n.label}</span>{n.count&&<b>{n.count}</b>}</button>)}<p className="nav-section">系统设置</p><button className="nav-item"><Settings size={19}/><span>系统管理</span></button></nav>
   <div className="sidebar-foot"><div className="avatar">张</div><div><strong>张海峰</strong><small>系统管理员</small></div><ChevronRight size={16}/></div>
  </aside>
  <section className="workspace"><Topbar/>{view==='dashboard'?<Dashboard/>:view==='posts'?<Posts/>:<Talent/>}</section>
 </main>;
}

function Topbar(){return <header className="topbar"><label className="search"><Search size={18}/><input aria-label="全局搜索" placeholder="搜索候选人、岗位…"/><kbd>⌘ K</kbd></label><div className="top-actions"><button aria-label="通知"><Bell size={19}/><i/></button><span>2026年9月3日 · 星期四</span></div></header>}

function Dashboard(){return <div className="content">
 <div className="page-head"><div><p>上午好，张海峰</p><h1>工作台</h1><span>今天有 12 份简历待审核，3 场面试需要关注。</span></div><button className="primary-button">＋ 新建岗位</button></div>
 <div className="stat-grid">{stats.map(s=><article className={`stat-card ${s[3]}`} key={s[0]}><span>{s[0]}</span><strong>{s[1]}</strong><small>{s[2]}</small></article>)}</div>
 <div className="dashboard-grid"><article className="panel candidate-panel"><PanelHead title="待办审核" sub="按提交时间排序"/>
  <div>{candidates.map(c=><div className="candidate-row" key={c[0]}><div className="person-avatar">{c[0][0]}</div><div className="person"><strong>{c[0]}</strong><span>{c[1]}</span></div><div className="score"><span>综合得分</span><strong>{c[2]}</strong></div><span className={`status ${c[4]}`}>{c[3]}</span><button className="row-action">去审核</button></div>)}</div></article>
  <aside className="panel schedule-panel"><PanelHead title="今日面试" sub="9月3日" icon/><Schedule time="09:30" post="信息化项目经理" meta="第三会议室 · 3人" live/><Schedule time="14:00" post="集团财务经理" meta="第一会议室 · 2人"/><Schedule time="16:30" post="品牌运营总监" meta="线上会议 · 1人"/><button className="outline-button">查看面试日程</button></aside></div>
 </div>}

function PanelHead({title,sub,icon}:{title:string,sub:string,icon?:boolean}){return <div className="panel-head"><div><h2>{title}</h2><span>{sub}</span></div><button>{icon?<Clock3 size={17}/>:<>查看全部 <ChevronRight size={15}/></>}</button></div>}
function Schedule({time,post,meta,live}:{time:string,post:string,meta:string,live?:boolean}){return <div className="schedule-item"><time>{time}</time><div><strong>{post}</strong><span>{meta}</span></div><i className={live?'live':''}>{live?'进行中':'待开始'}</i></div>}

function Posts(){return <div className="content"><div className="page-head"><div><p>职位管理 / 岗位列表</p><h1>岗位管理</h1><span>统一维护招聘岗位、任职标准与题卷版本。</span></div><button className="primary-button"><Plus size={15}/> 新建岗位</button></div>
 <div className="toolbar panel"><div className="tabset"><button className="selected">全部岗位 <b>12</b></button><button>招聘中 <b>8</b></button><button>储备中 <b>3</b></button><button>已停止 <b>1</b></button></div><div className="tool-right"><label><Search size={16}/><input placeholder="搜索岗位名称"/></label><button><SlidersHorizontal size={16}/> 筛选</button></div></div>
 <article className="panel data-panel"><table><thead><tr><th>岗位信息</th><th>招聘状态</th><th>预登记</th><th>已答题</th><th>待审核</th><th>题卷版本</th><th>操作</th></tr></thead><tbody>{posts.map((p,i)=><tr key={p[0]}><td><strong>{p[0]}</strong><span>{p[1]}</span></td><td><em className={p[2]==='招聘中'?'dot-status active':'dot-status'}>{p[2]}</em></td><td>{p[3]} 人</td><td>{p[4]} 人</td><td>{p[5]} 人</td><td><span className="version">V{3-i}.0 · 已发布</span></td><td><button className="icon-button"><QrCode size={17}/></button><button className="link-button">管理</button><button className="icon-button"><MoreHorizontal size={17}/></button></td></tr>)}</tbody></table></article>
 <div className="foot-note"><span>共 12 个岗位</span><span>一个候选人只能预登记并应聘一个岗位</span></div></div>}

function Talent(){return <div className="content"><div className="page-head"><div><p>人才管理 / 审核中心</p><h1>人才审核</h1><span>查看测评明细，完成初审与终审决策。</span></div><button className="soft-button"><FileText size={15}/> 导出审核表</button></div>
 <div className="review-layout"><article className="panel review-list"><div className="review-filter"><strong>待审核人才</strong><span>12</span><button><Search size={16}/></button></div>{candidates.slice(0,3).map((c,i)=><button className={i===0?'review-person selected':'review-person'} key={c[0]}><div className="person-avatar">{c[0][0]}</div><div><strong>{c[0]}</strong><span>{c[1]} · 提交于 09-{i+1}</span></div><b>{c[2]}</b><ChevronRight size={15}/></button>)}</article>
 <article className="panel review-detail"><div className="profile-head"><div className="large-avatar">陈</div><div><h2>陈晓宁</h2><p>应聘：集团财务经理</p><span><Phone size={12}/> 138 **** 5682　·　本科　·　12年经验</span></div><em>待初审</em></div>
  <div className="score-overview"><div className="score-ring"><strong>84.6</strong><span>综合得分</span></div><ScoreBar label="综合测评" score="88.0" width="88%"/><ScoreBar label="基本素质" score="82.0" width="82%"/><ScoreBar label="专业能力" score="81.3" width="81.3%"/></div>
  <div className="detail-section"><div className="section-title"><h3>综合测评明细</h3><button>展开全部 <ChevronDown size={14}/></button></div><div className="dimension-grid"><Metric name="学历与院校" value="18 / 20"/><Metric name="工作经历" value="27 / 30"/><Metric name="专业资质" value="22 / 25"/><Metric name="技能与背景" value="21 / 25"/></div></div>
  <div className="review-actions"><button className="reject">不予推荐</button><button className="pass"><Check size={16}/> 推荐进入面试</button></div>
 </article></div></div>}
function ScoreBar({label,score,width}:{label:string,score:string,width:string}){return <div className="bar-item"><span>{label}</span><div><i style={{width}}/></div><strong>{score}</strong></div>}
function Metric({name,value}:{name:string,value:string}){return <div className="metric"><span>{name}</span><strong>{value}</strong></div>}

function MobilePreview({view}:{view:View}){return <main className="mobile-stage"><div className="phone"><div className="phone-status"><b>9:41</b><span>● ● ▰</span></div>{view==='apply'?<ApplyPage/>:<InterviewPage/>}</div><aside className="mobile-note"><span>H5 移动端</span><h1>{view==='apply'?'应聘者扫码验证':'面试官扫码评分'}</h1><p>{view==='apply'?'手机号与岗位二维码双重校验，一个人只能应聘一个岗位。':'输入本人姓名并与场次名单精确匹配后，才可进入评分。'}</p><div><i/>适配微信内置浏览器</div><div><i/>关键操作大按钮设计</div></aside></main>}

function ApplyPage(){return <div className="mobile-page"><div className="mobile-brand"><span>任</span><b>任职管理系统</b></div><div className="mobile-hero"><small>应聘岗位</small><h2>集团财务经理</h2><p>集团总部 · 财务序列</p></div><section className="mobile-card"><div className="step"><i>1</i><span/><i>2</i><span/><i>3</i></div><h3>验证应聘身份</h3><p>请输入 HR 预登记的手机号码</p><label>手机号码<div><span>+86</span><input value="138 0013 8000" readOnly/></div></label><div className="safe-tip"><Check size={14}/> 信息仅用于核验本次应聘资格</div><button className="mobile-primary">验证并进入答题 <ChevronRight size={17}/></button></section><footer>三奇发展集团 · 人力资源中心</footer></div>}

function InterviewPage(){return <div className="mobile-page interview-page"><div className="mobile-brand light"><span>任</span><b>面试评分</b></div><div className="interview-hero"><div className="calendar"><b>03</b><span>SEP</span></div><div><small>今日面试场次</small><h2>集团财务经理</h2><p><Clock3 size={12}/> 14:00–16:00　<MapPin size={12}/> 第一会议室</p></div></div><section className="mobile-card login-card"><div className="identity-icon"><CircleUserRound size={27}/></div><h3>确认面试官身份</h3><p>请输入您在本场面试名单中的姓名</p><label>本人姓名<div className="name-input"><input value="李四" readOnly placeholder="请输入真实姓名"/></div></label><div className="safe-tip"><Check size={14}/> 姓名将与本场预设面试官名单匹配</div><button className="mobile-primary dark">确认身份并进入评分</button><small className="help">无法验证？请联系本场 HR 管理员</small></section></div>}
