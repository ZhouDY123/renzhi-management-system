<?php
declare(strict_types=1);

function personnel_score_catalog(): array {
    $rows=[];$add=function(string $category,string $code,string $name,string $label,string $type,array $rule,float $score)use(&$rows){$rows[]=[$category,$code,$name,$label,$type,json_encode($rule,JSON_UNESCAPED_UNICODE),$score];};
    $range=function($category,$code,$name,$label,$min,$max,$score)use($add){$rule=[];if($min!==null)$rule['min']=$min;if($max!==null)$rule['max']=$max;$add($category,$code,$name,$label,'range',$rule,$score);};
    foreach([[35,40,2.5],[40,45,2],[30,35,1.5],[45,50,1],[null,30,.5]] as [$min,$max,$score])$range('basic_info','age','年龄',($min===null?'30岁以下':$min.'至'.$max.'岁'),$min,$max,$score);
    foreach([['健康',2.5],['良好',2],['亚健康',1.5],['有慢性病',1],['严重疾患',0]] as [$v,$s])$add('basic_info','health','健康状况',$v,'eq',['value'=>$v],$s);
    foreach([['硕士及以上',5,['硕士','博士']],['本科',4,['本科']],['专科',3,['专科','大专']],['高中',2,['高中']],['高中以下',1,['高中以下']]] as [$label,$s,$vals])$add('exp_qualification','edu','学历',$label,'in',['values'=>$vals],$s);
    foreach([['双一流院校或国外同等级别院校',5],['一流院校或国外同等级别院校',4],['一本院校或国外同等级别院校',3],['二本院校',2],['三本及以下',1]] as [$v,$s])$add('exp_qualification','school','毕业院校',$v,'eq',['value'=>$v],$s);
    foreach([['会计学、财务管理、会计电算化、财政学、金融等专业',5],['统计学、经济管理、工商管理等相关专业',4.5],['其他文、理科专业',4],['其他工、农科专业',3],['艺术类专业',2]] as [$v,$s])$add('exp_qualification','major','专业',$v,'eq',['value'=>$v],$s);
    foreach([['正高及以上',3],['副高',2.5],['中级',2],['初级',1.5],['无',0]] as [$v,$s])$add('exp_qualification','title','职称',$v,'eq',['value'=>$v],$s);
    foreach([['中共党员',2],['中共预备党员',1],['国内其他党派党员',.5],['群众',0]] as [$v,$s])$add('exp_qualification','politics','政治面貌',$v,'eq',['value'=>$v],$s);
    foreach([[15,null,2.5],[10,15,2],[8,10,1.5],[5,8,1],[null,5,.5]] as [$min,$max,$s])$range('work_history','work_years','参加工作年限',($min===null?'5年以下':($max===null?$min.'年以上':$min.'至'.$max.'年')),$min,$max,$s);
    foreach([[10,null,2.5],[8,10,2],[5,8,1.5],[3,5,1],[null,3,.5]] as [$min,$max,$s])$range('work_history','prof_years','从事专业年限',($min===null?'3年以下':($max===null?$min.'年以上':$min.'至'.$max.'年')),$min,$max,$s);
    $career=function(string $code,string $name,array $tiers)use($add){foreach($tiers as [$label,$min,$max,$mgmt,$score]){$rule=[];if($min!==null)$rule['min']=$min;if($max!==null)$rule['max']=$max;if($mgmt!==null)$rule['is_mgmt']=$mgmt;$add('work_history',$code,$name,$label,'bool_range',$rule,$score);}};
    $career('group_co','集团公司',[['5年以上集团公司高级管理职务工作履历',5,null,1,4],['3至5年集团公司高级管理职务工作履历',3,5,1,3.5],['5年以上集团公司非高级管理职务工作履历',5,null,0,3],['3至5年集团公司非高级管理职务工作履历',3,5,0,2.5],['3年以下集团工作经历',null,3,null,1.5]]);
    $career('listed_co','上市公司',[['3年以上上市公司高级管理职务工作履历',3,null,1,3],['1至3年上市公司高级管理职务工作履历',1,3,1,2],['3年以上上市公司非高级管理职务工作履历',3,null,0,1],['1至3年上市公司非高级管理职务工作履历',1,3,0,.5],['1年以下上市公司工作经历',null,1,null,0]]);
    $career('private_co','非上市公司',[['3年以上非上市公司高级管理职务工作履历',3,null,1,2],['1至3年非上市公司高级管理职务工作履历',1,3,1,1.5],['5年以上非上市公司非高级管理职务工作履历',5,null,0,1],['3至5年非上市公司非高级管理职务工作履历',3,5,0,.5],['3年以下非上市公司工作经历',null,3,null,0]]);
    foreach([['本行业标杆企业',5],['本行业知名企业',4.5],['本行业一般企业',4],['本行业外头部企业',3.5],['本行业外一般企业',3]] as [$v,$s])$add('work_history','work_bg','工作背景',$v,'eq',['value'=>$v],$s);
    foreach([['Word/EXCEL/PPT/CAD熟练运用四种',2],['Word/EXCEL/PPT/CAD熟练运用三种',1.5],['Word/EXCEL/PPT/CAD熟练运用二种',1],['Word/EXCEL/PPT/CAD熟练运用一种',.5],['以上四种均不会',0]] as [$v,$s])$add('skills','computer','计算机基本操作',$v,'eq',['value'=>$v],$s);
    foreach([['掌握两门及以上外语并且口语熟练',2],['掌握一门外语并且口语熟练',1.5],['掌握一门外语，能进行基本的听说读写',1],['仅认识简单单词，达不到基本的听说读写程度',0]] as [$v,$s])$add('skills','language','外语',$v,'eq',['value'=>$v],$s);
    return $rows;
}
