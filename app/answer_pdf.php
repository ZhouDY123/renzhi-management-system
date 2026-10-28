<?php
// Self-contained A4 exporter. Only the authenticated answer document is read;
// scripts, navigation and unrelated page data never enter the PDF.
function assessment_pdf_from_html(string $html): string {
 $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
 $dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
 $xpath=new DOMXPath($dom);$root=$xpath->query('//main[contains(concat(" ",normalize-space(@class)," ")," answer-document ")]')->item(0);
 if(!$root)throw new RuntimeException('答卷内容无法导出');
 $blocks=[];
 $visit=function(DOMNode $node)use(&$visit,&$blocks):void{
  if(!$node instanceof DOMElement)return;
  $classes=explode(' ', $node->getAttribute('class'));$tag=strtolower($node->tagName);
  $text=trim($node->textContent);
  $add=function(string $text,int $size=11,bool $bold=false,bool $center=false,int $space=8)use(&$blocks){if(trim($text)!=='')$blocks[]=['text'=>$text,'size'=>$size,'bold'=>$bold,'center'=>$center,'space'=>$space];};
  if(in_array('answer-preview-heading',$classes,true)){foreach($node->childNodes as $child)if($child instanceof DOMElement)$add(trim($child->textContent),$child->tagName==='h1'?20:11,$child->tagName!=='p',true,10);return;}
  if(in_array('answer-score-summary',$classes,true)){foreach($node->childNodes as $child)if($child instanceof DOMElement)$add(trim($child->textContent),$child->tagName==='b'?14:10,$child->tagName==='b',true,10);return;}
  if(in_array('answer-preview-profile',$classes,true)){foreach($node->getElementsByTagName('div') as $row){$dt=$row->getElementsByTagName('dt')->item(0);$dd=$row->getElementsByTagName('dd')->item(0);if($dt&&$dd)$add($dt->textContent.'：'.$dd->textContent,11,false,false,5);}return;}
  if($tag==='h2'){$add($text,15,true,false,14);return;}
  if($tag==='h3'){$add($text,12,true,false,6);return;}
  if(in_array('answer-preview-options',$classes,true)){$options=[];foreach($node->childNodes as $child)if($child instanceof DOMElement)$options[]=trim($child->textContent);$add(implode('    ',$options),10,false,false,8);return;}
  if(in_array('answer-bonus-line',$classes,true)){$add($text,11,false,false,12);return;}
  if($tag==='p'){$add($text,in_array('answer-preview-note',$classes,true)?9:11,false,false,12);return;}
  foreach($node->childNodes as $child)$visit($child);
 };
 $visit($root);
 return assessment_pdf_document($blocks);
}
function assessment_pdf_document(array $blocks): string {
 $streams=[];$stream='';$y=790.0;$pageWidth=595.28;$width=491.28;$bottom=54.0;
 $measure=static function(string $char,float $size):float{return strlen($char)===1?$size*0.5:$size;};
 $draw=static function(string $text,float $x,float $y,float $size,bool $bold=false):string{
  $hex=strtoupper(bin2hex(iconv('UTF-8','UTF-16BE',$text)));
  return sprintf("BT /F1 %.2F Tf %s %.2F %.2F Td <%s> Tj ET\n",$size,$bold?'0.25 w 2 Tr':'0 Tr',$x,$y,$hex);
 };
 foreach($blocks as $block){
  $text=str_replace(["\r\n","\r",'☑','☐','✓'],["\n","\n",'[√]','[ ]','√'],$block['text']);
  $text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$text);$size=(float)$block['size'];$leading=$size*1.8;$lines=[];
  foreach(explode("\n",$text) as $paragraph){$line='';$used=0;foreach(preg_split('//u',$paragraph,-1,PREG_SPLIT_NO_EMPTY) as $char){$w=$measure($char,$size);if($used+$w>$width&&$line!==''){$lines[]=[$line,$used];$line='';$used=0;}$line.=$char;$used+=$w;}$lines[]=[$line,$used];}
  $needed=count($lines)*$leading+$block['space'];$reserve=$block['bold']?30:0;
  if($y-$bottom<$needed+$reserve&&$needed<150&&$stream!==''){$streams[]=$stream;$stream='';$y=790;}
  foreach($lines as [$line,$used]){if($y<$bottom+$leading){$streams[]=$stream;$stream='';$y=790;}$x=$block['center']?max(52,($pageWidth-$used)/2):52;$stream.=$draw($line,$x,$y,$size,$block['bold']);$y-=$leading;}
  $y-=$block['space'];
 }
 if($stream!==''||!$streams)$streams[]=$stream;
 // Standard Chinese CID font; PDF viewers resolve the GB1 glyph collection.
 $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type0 /BaseFont /STSong-Light /Encoding /UniGB-UCS2-H /DescendantFonts [4 0 R] >>',4=>'<< /Type /Font /Subtype /CIDFontType0 /BaseFont /STSong-Light /CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 4 >> /FontDescriptor 5 0 R /DW 1000 /W [1 95 500] >>',5=>'<< /Type /FontDescriptor /FontName /STSong-Light /Flags 6 /FontBBox [-25 -254 1000 880] /ItalicAngle 0 /Ascent 880 /Descent -120 /CapHeight 880 /StemV 80 >>'];
 $kids=[];$total=count($streams);foreach($streams as $index=>$content){$pageId=6+$index*2;$contentId=$pageId+1;$kids[]=$pageId.' 0 R';$content.=$draw('第 '.($index+1).' / '.$total.' 页',270,28,9);$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';}
 $objects[2]='<< /Type /Pages /Count '.$total.' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
 $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
 $xref=strlen($pdf);$count=count($objects)+1;$pdf.="xref\n0 $count\n0000000000 65535 f \n";for($i=1;$i<$count;$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
 return $pdf."trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}
