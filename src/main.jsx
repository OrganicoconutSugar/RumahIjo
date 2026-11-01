import RoomArtwork from './RoomArtwork.jsx';
import React, { Suspense, useEffect, useMemo, useRef, useState } from 'react';

import { Canvas, useFrame } from '@react-three/fiber';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
gsap.registerPlugin(ScrollTrigger);
import * as THREE from 'three';
import { ArrowRight, ArrowUpRight, Home, Menu as MenuIcon, X, MoveHorizontal, MessageCircle, Leaf, Sparkle } from './Icons3D';
import './style.css';
import brandLogo from './assets/rumahijo-brand-logo.png';
import { getLandingRooms, landingRoomAvailability } from './landingData';
import RoomDetailsDialog from './RoomDetailsDialog';
import DirtBike from './DirtBike';

function Box({position, size, color, ...props}){return <mesh position={position} castShadow receiveShadow {...props}><boxGeometry args={size}/><meshStandardMaterial color={color}/></mesh>}
function DoorNumber({number}){
 const texture=useMemo(()=>{const canvas=document.createElement('canvas');canvas.width=128;canvas.height=128;const ctx=canvas.getContext('2d');ctx.fillStyle='#f8f6e9';ctx.fillRect(0,0,128,128);ctx.fillStyle='#254a31';ctx.font='bold 88px sans-serif';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText(String(number),64,68);const map=new THREE.CanvasTexture(canvas);map.colorSpace=THREE.SRGBColorSpace;return map},[number]);
 useEffect(()=>()=>texture.dispose(),[texture]);
 return <mesh position={[0,1.5,.065]}><planeGeometry args={[.22,.22]}/><meshBasicMaterial map={texture}/></mesh>
}
function Door({position, side=false, number}){
 return <group position={position} rotation={[0,side ? Math.PI/2 : 0,0]}>
  <Box position={[0,.62,.015]} size={[.49,1.24,.06]} color="#ae7958"/>
  <Box position={[0,.62,.055]} size={[.38,1.12,.03]} color="#c18c68"/>
  <Box position={[.14,.59,.08]} size={[.04,.06,.03]} color="#e5d3a1"/>
  <Box position={[0,1.29,.02]} size={[.57,.08,.08]} color="#f7f6ed"/>
  <Box position={[0,1.49,.02]} size={[.52,.2,.04]} color="#98b5a6"/>
  <DoorNumber number={number}/>
 </group>
}
function HouseSign(){
 const texture=useMemo(()=>{
  const canvas=document.createElement('canvas'); canvas.width=1024; canvas.height=256;
  const ctx=canvas.getContext('2d'); ctx.fillStyle='#f5f5eb';ctx.fillRect(0,0,1024,256);
  ctx.fillStyle='#315b3d';ctx.font='bold 145px sans-serif';ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillText('RumahIjo',512,132);
  const map=new THREE.CanvasTexture(canvas);map.colorSpace=THREE.SRGBColorSpace;return map;
 },[]);
 useEffect(()=>()=>texture.dispose(),[texture]);
 return <mesh position={[-.45,1.08,-1.285]}><planeGeometry args={[1.02,.255]}/><meshBasicMaterial map={texture}/></mesh>
}
function TiledRoof(){
 return <group position={[1.7,3.62,-2.1]}>
  {/* Two sloping tiled planes meet at the ridge. */}
  {[-1,1].map(side=><group key={side} position={[side*.55,.29,0]} rotation={[0,0,-side*.48]}>
   <Box position={[0,0,0]} size={[1.27,.12,1.9]} color="#708c6d"/>
   {Array.from({length:9},(_,i)=><Box key={i} position={[0,.075,-.8+i*.2]} size={[1.27,.045,.04]} color="#4e7054"/>)}
   {[-.35,0,.35].map(x=><Box key={x} position={[x,.078,0]} size={[.025,.025,1.86]} color="#83a081"/>)}
  </group>)}
  <Box position={[0,.6,0]} size={[.12,.12,1.98]} color="#4a6a50"/>
 </group>
}
function RoofWing({position,length,rotation=0}){
 return <group position={position} rotation={[0,rotation,0]}>
  {[-1,1].map(side=><group key={side} position={[0,.24,side*.43]} rotation={[side*.48,0,0]}>
   <Box position={[0,0,0]} size={[length,.13,1.03]} color="#708c6d"/>
   {Array.from({length:Math.ceil(length/.24)},(_,i)=><Box key={i} position={[-length/2+.12+i*.24,.09,0]} size={[.035,.04,1.02]} color="#526f55"/>)}
   {[-.27,0,.27].map(z=><Box key={z} position={[0,.095,z]} size={[length,.035,.035]} color="#89a487"/>)}
  </group>)}
  <Box position={[0,.48,0]} size={[length,.12,.12]} color="#4b6950"/>
 </group>
}
function Rail({from,to}){
 const mid=useMemo(()=>new THREE.Vector3(...from).add(new THREE.Vector3(...to)).multiplyScalar(.5),[...from,...to]);
 const dir=useMemo(()=>new THREE.Vector3(...to).sub(new THREE.Vector3(...from)),[...from,...to]);
 const q=useMemo(()=>new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0,1,0),dir.clone().normalize()),[dir]);
 return <mesh position={mid} quaternion={q} castShadow><cylinderGeometry args={[.025,.025,dir.length(),8]}/><meshStandardMaterial color="#677c6a"/></mesh>
}
function SpiralStair(){
 const count=18, radius=.55, cx=3.13, cz=-.05, start=1.45, turn=Math.PI*2*1.25;
 const points=Array.from({length:count},(_,i)=>{const a=start+i*turn/(count-1);return {a,x:cx+radius*Math.cos(a),z:cz+radius*Math.sin(a),y:.12+i*2.06/(count-1)}});
 return <group>
  <mesh position={[cx,1.2,cz]} castShadow><cylinderGeometry args={[.075,.075,2.5,12]}/><meshStandardMaterial color="#687a6a"/></mesh>
  {points.map((p,i)=><group key={i}><Box position={[p.x,p.y,p.z]} size={[.62,.075,.24]} rotation={[0,-p.a,0]} color="#d4d5c8"/><mesh position={[cx+.83*Math.cos(p.a),p.y+.35,cz+.83*Math.sin(p.a)]}><cylinderGeometry args={[.025,.025,.7,8]}/><meshStandardMaterial color="#708674"/></mesh></group>)}
  {points.slice(1).map((p,i)=><Rail key={i} from={[cx+.83*Math.cos(points[i].a),points[i].y+.7,cz+.83*Math.sin(points[i].a)]} to={[cx+.83*Math.cos(p.a),p.y+.7,cz+.83*Math.sin(p.a)]}/>)}
  <Box position={[2.65,2.13,-.92]} size={[1.22,.16,.85]} color="#d8d8c8"/>
 </group>
}
function House({rotationTarget, reduced}){
 const group=useRef();
 useFrame((_,delta)=>{if(group.current){if(!rotationTarget.current.dragging&&!reduced) rotationTarget.current.y+=delta*.12; group.current.rotation.y += (rotationTarget.current.y-group.current.rotation.y)*Math.min(1,delta*8)}});

 return <group ref={group} position={[0,-.7,0]}>
  {/* The long, separate wings form an L around a courtyard. */}
  <Box position={[0,-.13,0]} size={[8.5,.24,7.2]} color="#bdcba9"/>
  <Box position={[0,.015,.2]} size={[6.4,.05,5.5]} color="#dce1ce"/>
  <Box position={[0,1,-2.1]} size={[6.9,2,1.55]} color="#f6f5eb"/>
  <Box position={[-2.675,1,.43]} size={[1.55,2,3.5]} color="#f2f2e7"/>
  <Box position={[1.75,2.04,-2.1]} size={[3.5,.15,1.78]} color="#d8d8c8"/>
  <RoofWing position={[-1.75,2.08,-2.1]} length={3.6}/>
  <RoofWing position={[-2.675,2.08,.43]} length={3.65} rotation={Math.PI/2}/>
  {/* Doors sit slightly in front of the inner wall surfaces, with space between them. */}
  {[-1.3,.4,2.1].map((x,i)=><Door key={'back'+x} number={i+1} position={[x,0,-1.29]}/>)}
  {[-.55,.6,1.72].map((z,i)=><Door key={'side'+z} number={i+4} position={[-1.87,0,z]} side/>)}
  <HouseSign/>
  <Box position={[1.7,2.82,-2.1]} size={[1.85,1.5,1.42]} color="#f8f7ee"/>
  <Door position={[1.7,2.08,-1.355]} number={7}/>
  <TiledRoof/>
  {/* Fencing surrounds the accessible upper terrace, leaving the stair entrance open. */}
  {[.18,.66,1.14,1.62,2.10,2.58].map(x=><Box key={'front'+x} position={[x,2.47,-1.17]} size={[.045,.8,.045]} color="#607b65"/>)}
  <Box position={[1.38,2.84,-1.17]} size={[2.45,.06,.06]} color="#607b65"/>
  {[-2.75,-2.3,-1.85,-1.4].map(z=><Box key={'right'+z} position={[3.43,2.47,z]} size={[.045,.8,.045]} color="#607b65"/>)}
  <Box position={[3.43,2.84,-2.07]} size={[.06,.06,1.55]} color="#607b65"/>
  {[.25,.8,1.35,1.9,2.45,3.0,3.43].map(x=><Box key={'back'+x} position={[x,2.47,-2.94]} size={[.045,.8,.045]} color="#607b65"/>)}
  <Box position={[1.84,2.84,-2.94]} size={[3.25,.06,.06]} color="#607b65"/>
  <SpiralStair/>
  <DirtBike position={[-.55,.02,2.08]} color="#c52f28" rotation={-.12}/>
  <DirtBike position={[1.3,.02,2.65]} color="#263747" rotation={-.12}/>
  <Box position={[-3.65,.28,2.65]} size={[.15,.55,.15]} color="#896d53"/>
  <mesh position={[-3.65,.83,2.65]} castShadow><sphereGeometry args={[.45,9,7]}/><meshStandardMaterial color="#9eb77d"/></mesh>
  <Box position={[2.55,.13,2.8]} size={[.4,.25,.4]} color="#c4a485"/>
  <mesh position={[2.55,.44,2.8]}><sphereGeometry args={[.29,8,6]}/><meshStandardMaterial color="#789b6f"/></mesh>
 </group>
}
class HouseBoundary extends React.Component {constructor(p){super(p);this.state={failed:false}} static getDerivedStateFromError(){return {failed:true}} render(){return this.state.failed?<div className="house-fallback"><Home size={110}/><span>RumahIjo</span></div>:this.props.children}}
function HouseViewer(){const target=useRef({y:0,dragging:false}); const last=useRef(0); const velocity=useRef(0); const [reduced,setReduced]=useState(false);
 useEffect(()=>{const m=window.matchMedia('(prefers-reduced-motion: reduce)');const update=()=>setReduced(m.matches);update();m.addEventListener('change',update);return()=>m.removeEventListener('change',update)},[]);
 function down(e){e.currentTarget.setPointerCapture(e.pointerId);target.current.dragging=true;last.current=e.clientX;velocity.current=0;gsap.killTweensOf(target.current)}
 function move(e){if(!target.current.dragging)return;let dx=e.clientX-last.current;last.current=e.clientX;velocity.current=dx*.012;target.current.y+=velocity.current}
 function up(){if(!target.current.dragging)return;target.current.dragging=false;gsap.to(target.current,{y:target.current.y+velocity.current*8,duration:1.1,ease:'power3.out'})}
 return <div className="viewer" onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up} role="img" aria-label="Ilustrasi rumah kost berbentuk L dengan enam pintu di bawah, satu pintu di atas, dan tangga spiral, atap genteng, pagar dan dua motor trail. Geser untuk memutar"><HouseBoundary><Canvas shadows dpr={[1,1.5]} camera={{position:[10,9,11],fov:38}} gl={{antialias:true,alpha:true}}><ambientLight intensity={1.8}/><directionalLight position={[5,9,5]} intensity={2.4} castShadow/><Suspense fallback={null}><House rotationTarget={target} reduced={reduced}/></Suspense></Canvas></HouseBoundary></div>
}
function TypingHeading(){
 const full='Ruang yang pas untuk ceritamu.'; const split='Ruang yang pas'.length;
 const ref=useRef(null); const [visible,setVisible]=useState(false); const [count,setCount]=useState(0); const [reduced,setReduced]=useState(false);
 useEffect(()=>{const media=window.matchMedia('(prefers-reduced-motion: reduce)');const update=()=>setReduced(media.matches);update();media.addEventListener('change',update);return()=>media.removeEventListener('change',update)},[]);
 useEffect(()=>{const showStatic=()=>{setVisible(false);setCount(full.length)};const trigger=ScrollTrigger.create({trigger:ref.current,start:'top 92%',end:'bottom top',onEnter:()=>setVisible(true),onEnterBack:showStatic,onLeave:showStatic,onLeaveBack:showStatic,onUpdate:self=>{if(self.direction<0)showStatic()}});return()=>trigger.kill()},[]);
 useEffect(()=>{if(!visible||reduced)return;let timer;let current=0;setCount(0);const tick=()=>{current++;setCount(current);timer=setTimeout(current>=full.length?()=>{current=0;setCount(0);timer=setTimeout(tick,400)}:tick,current>=full.length?1900:85)};timer=setTimeout(tick,250);return()=>clearTimeout(timer)},[visible,reduced]);
 const amount=reduced?full.length:count;
 return <h2 ref={ref} className="typing-heading" aria-label={full}><span className="typing-reserve" aria-hidden="true">Ruang yang pas<br/>untuk ceritamu.</span><span className="typing-text" aria-hidden="true">{full.slice(0,Math.min(amount,split))}<br/>{full.slice(split,Math.min(amount,split+7))}<span>{full.slice(split+7,amount)}</span><i className="typing-cursor"/></span></h2>
}
function RevealHeading(){
 const ref=useRef(null);const [visible,setVisible]=useState(false);
 useEffect(()=>{const observer=new IntersectionObserver(([entry])=>setVisible(entry.isIntersecting),{threshold:.3});if(ref.current)observer.observe(ref.current);return()=>observer.disconnect()},[]);
 return <h2 ref={ref} className={'reveal-heading '+(visible?'is-visible':'')} aria-label="Lebih fleksibel, lebih nyaman."><span className="reveal-line" aria-hidden="true"><span>Lebih fleksibel,</span></span><span className="reveal-line" aria-hidden="true"><span>lebih nyaman.</span></span></h2>
}
function useVisible(ref){
 const [visible,setVisible]=useState(false);
 useEffect(()=>{const observer=new IntersectionObserver(([entry])=>setVisible(entry.isIntersecting),{threshold:.15});if(ref.current)observer.observe(ref.current);return()=>observer.disconnect()},[ref]);
 return visible;
}
function ContactHeading(){
 const ref=useRef(null);const [mode,setMode]=useState('hidden');
 useEffect(()=>{const trigger=ScrollTrigger.create({trigger:ref.current,start:'top 92%',end:'bottom top',onEnter:()=>setMode('animate'),onEnterBack:()=>setMode('instant'),onLeaveBack:()=>setMode('hidden')});return()=>trigger.kill()},[]);
 return <h2 ref={ref} className={'contact-heading '+(mode==='animate'?'is-visible':mode==='instant'?'is-instant':'')} aria-label="Mulai cerita barumu di sini."><span aria-hidden="true" className="contact-words"><span>Mulai</span> <span>cerita</span><br/><span>barumu</span> <span>di sini.</span></span></h2>
}
function PlanCards({onChoose}){
 const ref=useRef(null);const visible=useVisible(ref);
 const plans=[['01','Harian','Untuk kamu yang ingin tinggal lebih singkat.','Rp95.000 / hari'],['02','Bulanan','Pilihan fleksibel untuk tinggal lebih lama.','Rp650.000–Rp800.000 / bulan'],['03','Tahunan','Pilihan untuk rencana yang lebih panjang.','Rp6.000.000–Rp7.500.000 / tahun']];
 return <div ref={ref} className={'plans '+(visible?'plans-visible':'')}>{plans.map(([n,title,desc,price])=><article className="plan" key={n}><span className="plan-num">{n} / 03</span><h3>{title}</h3><p>{desc}</p><button className="plan-bottom" onClick={()=>onChoose(title)} aria-label={'Tanyakan paket '+title}><span>{price}</span><ArrowUpRight size={20}/></button></article>)}</div>
}

function ScrollIntroductions(){
 useEffect(()=>{
  const media=gsap.matchMedia();
  media.add('(prefers-reduced-motion: no-preference)',()=>{
   const selector=['main section h1','main section h2:not(.typing-heading):not(.contact-heading)','main section > p','.eyebrow','.hero-copy > p','.hero-copy > .primary','.hero-bottom','.visual-heading','.section-label','.mini-heading','.section-top > p','.menu-intro > p','.section-row .filters','.property-card','.plan','.availability-head > div','.room-toolbar','.room','.room-hint','.contact-copy > p','.contact-details > div','.contact-form','.footer-top > div','.footer-location .location-head','.footer-location iframe','.footer-bottom'].join(',');
   const targets=gsap.utils.toArray(selector);const triggers=targets.map((el)=>{
    let tween;const reset=()=>{if(tween)tween.kill();gsap.set(el,{clearProps:'opacity,transform,visibility'})};
    return ScrollTrigger.create({trigger:el,start:'top 92%',onEnter:()=>{const siblings=el.parentElement;const i=siblings?Array.from(siblings.children).indexOf(el):0;tween=gsap.fromTo(el,{opacity:0,y:28},{opacity:1,y:0,duration:1.2,delay:Math.min(i%6*.14,.56),ease:'power2.out',overwrite:true,onComplete:()=>gsap.set(el,{clearProps:'opacity,transform,visibility'})})},onEnterBack:reset,onLeaveBack:reset});
   });return ()=>{triggers.forEach(t=>t.kill());targets.forEach(el=>gsap.set(el,{clearProps:'opacity,transform,visibility'}))};
  });return ()=>media.revert();
 },[]);return null;
}
export default function App({roomTypes=[], loggedIn=false, dashboardUrl="/penyewa/dashboard"}){
 const rooms=getLandingRooms(roomTypes);
 const inventory=landingRoomAvailability;const [nav,setNav]=useState(false),[active,setActive]=useState('kostku'),[filter,setFilter]=useState('Semua'),[roomFilter,setRoomFilter]=useState('Semua'),[message,setMessage]=useState(''),[toast,setToast]=useState('');
 const [selectedRoom,setSelectedRoom]=useState(null);
 useEffect(()=>{let observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting)setActive(e.target.id)}),{rootMargin:'-25% 0px -60% 0px'});document.querySelectorAll('main section[id]').forEach(s=>observer.observe(s));return()=>observer.disconnect()},[]);
 useEffect(()=>{if(!toast)return;let t=setTimeout(()=>setToast(''),2500);return()=>clearTimeout(t)},[toast]);
 const links=[['kostku','Kostku'],['property','Property'],['menu','Menu'],['contact','Contact']];
 function contactFromDialog(inquiry){setMessage(inquiry);setSelectedRoom(null);requestAnimationFrame(()=>document.querySelector('#contact').scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'}));}
 function selectType(type){setMessage(`Halo RumahIjo, saya ingin bertanya tentang KamarIjoku ${type}.`);document.querySelector('#contact').scrollIntoView({behavior:'smooth'});}
 function selectPlan(plan){setMessage(`Halo RumahIjo, saya ingin bertanya tentang paket sewa ${plan}.`);document.querySelector('#contact').scrollIntoView({behavior:'smooth'});}
 function selectRoom(id){setMessage(`Halo RumahIjo, saya ingin bertanya tentang ketersediaan kamar ${id}.`);document.querySelector('#contact').scrollIntoView({behavior:'smooth'});}
 function submit(e){e.preventDefault();const data=new FormData(e.currentTarget);const text=`Halo RumahIjo, saya ${String(data.get('nama')).trim()}.\nNomor HP: ${String(data.get('telepon')).trim()}\n\n${String(data.get('pesan')).trim()}`;window.open(`https://wa.me/6285117473635?text=${encodeURIComponent(text)}`,'_blank','noopener,noreferrer')}
 return <><ScrollIntroductions/><header className="nav-wrap"><nav className="nav"><a className="brand" href="#kostku"><span className="brand-icon"><img className="brand-logo" src={brandLogo} width="52" height="52" alt=""/></span>Rumah<span>Ijo.</span></a><div className={'nav-links '+(nav?'open':'')}>{links.map(([id,label])=><a key={id} className={active===id?'active':''} href={'#'+id} onClick={()=>setNav(false)}>{label}</a>)}<div className="mobile-auth"><a href={loggedIn?dashboardUrl:"/login"}>{loggedIn?"Dashboard":"Login"}</a></div></div><div className="auth"><a className="sign" href={loggedIn?dashboardUrl:"/login"}>{loggedIn?"Dashboard":"Login"} <ArrowUpRight size={15}/></a></div><button className="hamburger" aria-label={nav?'Tutup menu':'Buka menu'} aria-expanded={nav} onClick={()=>setNav(!nav)}>{nav?<X/>:<MenuIcon/>}</button></nav></header>
 <main><section id="kostku" className="hero-section"><div className="hero-grid"><div className="hero-copy"><div className="eyebrow"><span className="small-dot"/> TEMPAT PULANG YANG NYAMAN</div><h1>Kost nyaman,<br/><span>rasa rumah</span><br/>sendiri.</h1><p>Ruang untuk beristirahat, bertumbuh, dan menjalani hari dengan lebih tenang.</p><a className="primary" href="#property">Jelajahi kost <ArrowUpRight size={18}/></a><div className="hero-bottom"><div className="round-mark"><Leaf size={21}/></div><div><strong>Ruang yang terasa pas.</strong><small>Untuk cerita barumu.</small></div><span className="hero-index">01 <em>/ 04</em></span></div></div><div className="hero-visual"><div className="visual-top"><span>RUMAHIJO / KOSTKU</span><span>EST. FOR LIVING <ArrowUpRight size={14}/></span></div><div className="visual-heading"><div><span className="visual-label">A LITTLE PLACE TO CALL HOME</span><h2>Temukan tempat<br/>yang terasa <i>tepat.</i></h2></div><div className="tags"><span>Kost</span><span>Rent</span><span>Home</span></div></div><HouseViewer/><div className="visual-bottom"><div className="drag-icon"><MoveHorizontal size={20}/></div><span>Geser untuk memutar rumah</span><span className="visual-number">01 <small>/ 01</small></span></div></div></div><div className="marquee" aria-label="For Rent"><div className="marquee-track" aria-hidden="true">{[0,1].map(i=><div className="marquee-group" key={i}>{Array.from({length:4},(_,j)=><React.Fragment key={j}><span>For Rent</span><Sparkle size={32}/></React.Fragment>)}</div>)}</div></div></section>
 <section id="property" className="property section"><div className="section-top"><div><span className="section-label">KENALAN DENGAN RUMAHIJO</span><TypingHeading/></div><p>Setiap orang punya cara sendiri untuk merasa di rumah. Temukan ruang yang sesuai dengan kebutuhanmu.</p></div><div className="section-row"><div><span className="mini-heading">PILIHAN KAMAR</span></div><div className="filters" aria-label="Filter properti">{['Semua',...rooms.map(r=>r.type)].map(f=><button className={filter===f?'selected':''} onClick={()=>setFilter(f)} key={f}>{f}</button>)}</div></div><div className="property-grid">{rooms.filter(r=>filter==='Semua'||r.type===filter).map((r,i)=><article className="property-card" key={r.id}><button type="button" className="property-open" aria-label={'Lihat detail KamarIjoku '+r.type} aria-haspopup="dialog" onClick={()=>setSelectedRoom(r)}/><div className={'property-image image-'+r.id}><RoomArtwork type={r.type}/><span className="card-type">{r.type}</span><span className="card-count">{r.id} / 03</span></div><div className="property-info"><div className="property-title"><div><span>GAMBAR KAMAR</span><h3>{r.name}</h3></div><button onClick={event=>{event.stopPropagation();selectType(r.type)}} aria-label={'Tanyakan '+r.name+' '+r.type}><ArrowUpRight size={20}/></button></div><div className="room-details">{r.description&&<p className="room-description">{r.description}</p>}{r.features.length>0&&<div className="feature-list">{r.features.map(f=><span key={f}>{f}</span>)}</div>}</div><div className="price"><span>Harga sewa</span><strong>{r.price}</strong></div></div></article>)}</div></section>
 <section id="menu" className="menu-section section"><div className="menu-intro"><div><span className="section-label">PILIH CARA TINGGALMU</span><h2>Lebih fleksibel,<br/><span>lebih nyaman.</span></h2></div><p>Pilih durasi sewa yang paling cocok untuk rencanamu.</p></div><PlanCards onChoose={selectPlan}/><div className="availability"><div className="availability-head"><div><span className="section-label">KETERSEDIAAN KAMAR</span><h2>Cari <span>ruangmu.</span></h2><p>Ketersediaan dapat berubah. Hubungi pengelola untuk memastikan.</p></div><div className="room-stats"><div><strong>{inventory.filter(x=>x.status==='Kosong').length.toString().padStart(2,'0')}</strong><span><i className="status-dot empty"/> Kosong</span></div><div><strong>{inventory.filter(x=>x.status==='Terisi').length.toString().padStart(2,'0')}</strong><span><i className="status-dot occupied"/> Terisi</span></div></div></div><div className="room-toolbar"><span>NOMOR KAMAR</span><div className="filters">{['Semua','Kosong','Terisi'].map(f=><button key={f} className={roomFilter===f?'selected':''} onClick={()=>setRoomFilter(f)}>{f}</button>)}</div></div><div className="rooms">{inventory.filter(r=>roomFilter==='Semua'||r.status===roomFilter).map(r=><button key={r.id} className={'room '+(r.status==='Terisi'?'taken':'')} disabled={r.status==='Terisi'} onClick={()=>selectRoom(r.id)}><span className="room-top"><span>{r.id}</span><Home size={26}/></span><small><i className={'status-dot '+(r.status==='Kosong'?'empty':'occupied')}/>{r.status}</small></button>)}</div><p className="room-hint">Pilih kamar kosong untuk menanyakan detailnya. <ArrowRight size={15}/></p></div></section>
 <section id="contact" className="contact-section section"><div className="contact-copy"><span className="section-label">MARI BERKENALAN</span><ContactHeading/><p>Punya pertanyaan tentang RumahIjo? Tinggalkan pesan dan mari temukan ruang yang tepat untukmu.</p><div className="contact-details"><div><span>WHATSAPP</span><strong><a href="https://wa.me/6285117473635" target="_blank" rel="noopener noreferrer">0851-1747-3635</a></strong></div><div><span>LOKASI</span><strong><a href="https://www.google.com/maps/search/?api=1&amp;query=-2.95780556%2C104.73394444" target="_blank" rel="noopener noreferrer">KM 4,5, Palembang</a></strong></div><div><span>EMAIL</span><strong><a href="mailto:fairuzharits@gmail.com">fairuzharits@gmail.com</a></strong></div></div></div><form className="contact-form" onSubmit={submit}><div className="form-title"><div><span className="section-label">KIRIM PESAN</span><h3>Ada yang ingin ditanyakan?</h3></div><div className="form-icon"><MessageCircle size={21}/></div></div><label>Nama<input required name="nama" placeholder="Nama lengkapmu"/></label><label>No. HP<input required name="telepon" type="tel" placeholder="Nomor yang bisa dihubungi"/></label><label>Pesan<textarea required name="pesan" rows="4" value={message} onChange={e=>setMessage(e.target.value)} placeholder="Ceritakan kebutuhanmu di sini"/></label><button className="primary form-button" type="submit">Chat via WhatsApp <ArrowUpRight size={18}/></button><small>Pesan akan terbuka di WhatsApp untuk diperiksa sebelum dikirim.</small></form></section></main>
 <footer><div className="footer-location"><div className="location-head"><div><span className="section-label">LOKASI RUMAHIJO</span><h2>Temukan kami di sini.</h2><p>KM 4,5, Palembang</p></div><a className="footer-map" href="https://www.google.com/maps/search/?api=1&amp;query=-2.95780556%2C104.73394444" target="_blank" rel="noopener noreferrer">Buka di Google Maps <ArrowUpRight size={16}/></a></div><iframe title="Peta lokasi RumahIjo di KM 4,5 Palembang" src="https://maps.google.com/maps?q=-2.95780556%2C104.73394444&amp;z=17&amp;output=embed" loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen></iframe></div><div className="footer-bottom"><span>© 2026 RumahIjo. Semua hak dilindungi.</span><span>MADE FOR FEELING AT HOME</span></div></footer>{selectedRoom&&<RoomDetailsDialog key={selectedRoom.id} room={selectedRoom} onClose={()=>setSelectedRoom(null)} onContact={contactFromDialog}/>}{toast&&<div role="status" className="toast">{toast}</div>}</>
}
