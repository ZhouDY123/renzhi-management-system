/* Drag handles live outside the editable document; sizes persist in the image URL fragment. */
window.attachPracticalImageResize = (editor, host) => {
  const layer = document.createElement('div');
  layer.className = 'practical-image-resize';layer.hidden = true;
  layer.innerHTML = '<span class="practical-image-size-label"></span><button type="button" data-resize="e" aria-label="拖动调整图片宽度"></button><button type="button" data-resize="s" aria-label="拖动调整图片高度"></button><button type="button" data-resize="se" aria-label="拖动等比例缩放图片"></button>';
  host.append(layer);
  let selected = null, drag = null, frame = 0;
  const images = () => [...host.querySelectorAll('.toastui-editor-ww-container img[src]')];
  const clamp = value => Math.max(32,Math.min(2000,Math.round(value)));
  function layout() {
    if (!selected?.isConnected || !editor.isWysiwygMode()) { layer.hidden = true;return; }
    const rect = selected.getBoundingClientRect(), root = host.getBoundingClientRect();
    const viewport = selected.closest('.toastui-editor-ww-container').getBoundingClientRect();
    if (!rect.width || rect.bottom <= viewport.top || rect.top >= viewport.bottom) { layer.hidden=true;return; }
    layer.hidden=false;layer.style.left=`${rect.left-root.left}px`;layer.style.top=`${rect.top-root.top}px`;
    layer.style.width=`${rect.width}px`;layer.style.height=`${rect.height}px`;
    const size=drag?.size || practicalImageSize.read(selected.getAttribute('src')) || {width:Math.round(rect.width),height:Math.round(rect.height)};
    layer.querySelector('span').textContent=`${size.width} × ${size.height} · 角点等比例，边点调宽高`;
  }
  function refresh() {
    if (!drag) images().forEach(img=>practicalImageSize.apply(img,practicalImageSize.read(img.getAttribute('src'))));
    layout();
  }
  function schedule() { if(!frame)frame=requestAnimationFrame(()=>{frame=0;refresh();}); }
  editor.addCommand('wysiwyg','resizePracticalImage',({index,src,size},state,dispatch)=>{
    let current=0,found=false;
    state.doc.descendants((node,pos)=>{
      if(node.type.name==='image') {
        if(current++===index && node.attrs.imageUrl===src) {
          const imageUrl=src.replace(/#practical-size=\d+x\d+$/,'')+`#practical-size=${size.width}x${size.height}`;
          dispatch(state.tr.setNodeMarkup(pos,undefined,{...node.attrs,imageUrl}));found=true;
        }
      }
    });
    return found;
  });
  function commit(size) {
    if(!selected?.isConnected)return;
    const index=images().indexOf(selected),src=selected.getAttribute('src');
    editor.exec('resizePracticalImage',{index,src,size});
    selected=images()[index] || null;schedule();
  }
  host.addEventListener('click',event=>{
    const image=event.target.closest('.toastui-editor-ww-container img[src]');
    if(image){selected=image;layout();}
    else if(!layer.contains(event.target)){selected=null;layer.hidden=true;}
  });
  host.addEventListener('dragstart',event=>{if(event.target.matches('.toastui-editor-ww-container img[src]'))event.preventDefault();});
  layer.addEventListener('pointerdown',event=>{
    const handle=event.target.closest('[data-resize]');if(!handle||!selected)return;
    event.preventDefault();event.stopPropagation();
    const rect=selected.getBoundingClientRect();
    drag={handle,kind:handle.dataset.resize,x:event.clientX,y:event.clientY,width:rect.width,height:rect.height,size:{width:Math.round(rect.width),height:Math.round(rect.height)}};
    handle.setPointerCapture(event.pointerId);
  });
  layer.addEventListener('pointermove',event=>{
    if(!drag)return;
    let width=drag.width,height=drag.height;
    if(drag.kind==='e')width+=event.clientX-drag.x;
    else if(drag.kind==='s')height+=event.clientY-drag.y;
    else {
      const scale=Math.max(32/drag.width,32/drag.height,Math.min(2000/drag.width,2000/drag.height,1+((event.clientX-drag.x)/drag.width+(event.clientY-drag.y)/drag.height)/2));
      width*=scale;height*=scale;
    }
    drag.size={width:clamp(width),height:clamp(height)};
    practicalImageSize.apply(selected,drag.size);layout();
  });
  layer.addEventListener('pointerup',()=>{if(drag){const size=drag.size;drag=null;commit(size);}});
  layer.addEventListener('pointercancel',()=>{drag=null;refresh();});
  layer.addEventListener('keydown',event=>{
    if(!selected||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
    event.preventDefault();
    const rect=selected.getBoundingClientRect(),size=practicalImageSize.read(selected.getAttribute('src'))||{width:rect.width,height:rect.height};
    const step=event.shiftKey?10:1,delta=['ArrowLeft','ArrowUp'].includes(event.key)?-step:step;
    const kind=event.target.dataset.resize;
    if(kind==='e')size.width=clamp(size.width+delta);
    else if(kind==='s')size.height=clamp(size.height+delta);
    else {const ratio=size.height/size.width;size.width=clamp(size.width+delta);size.height=clamp(size.width*ratio);}
    commit(size);
  });
  new MutationObserver(schedule).observe(editor.getEditorElements().wwEditor,{childList:true,subtree:true,attributes:true,attributeFilter:['src']});
  host.addEventListener('load',schedule,true);host.addEventListener('scroll',schedule,true);
  new ResizeObserver(schedule).observe(host);
  editor.on('change',schedule);editor.on('changeMode',()=>{selected=null;schedule();});
  return ()=>{selected=null;drag=null;layer.hidden=true;schedule();};
};
