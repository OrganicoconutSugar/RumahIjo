import React, { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Canvas, useFrame } from '@react-three/fiber';
import { gsap } from 'gsap';
import * as THREE from 'three';
import { ArrowRight, ArrowUpRight, Home, Menu as MenuIcon, X, MoveHorizontal, MessageCircle, Leaf, Sparkle } from './Icons3D';
import './style.css';

const rooms = [
 {id:'01',type:'Type A',name:'KamarIjoku',price:'Rp800.000 / bulan',features:['WiFi','AC','KM dalam']},
 {id:'02',type:'Type B',name:'KamarIjoku',price:'Rp700.000 / bulan',features:['WiFi','Kipas','KM dalam']},
 {id:'03',type:'Type C',name:'KamarIjoku',price:'Rp650.000 / bulan',features:['WiFi','AC','KM dalam']},
];
const inventory = Array.from({length:10},(_,i)=>({id:String(i+1).padStart(2,'0'),status:[1,2,3,4,5,7].includes(i+1)?'Terisi':'Kosong'}));
function Box({position, size, color, ...props}){return <mesh position={position} castShadow receiveShadow {...props}><boxGeometry args={size}/><meshStandardMaterial color={color}/></mesh>}
function Door({position, side=false}){
 return <group position={position} rotation={[0,side ? Math.PI/2 : 0,0]}>
  <Box position={[0,.62,.015]} size={[.49,1.24,.06]} color="#ae7958"/>
  <Box position={[0,.62,.055]} size={[.38,1.12,.03]} color="#c18c68"/>
  <Box position={[.14,.59,.08]} size={[.04,.06,.03]} color="#e5d3a1"/>
  <Box position={[0,1.29,.02]} size={[.57,.08,.08]} color="#f7f6ed"/>
  <Box position={[0,1.49,.02]} size={[.52,.2,.04]} color="#98b5a6"/>
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
function House({rotationTarget, reduced}){
 const group=useRef();
 useFrame((_,delta)=>{if(group.current){if(!rotationTarget.current.dragging&&!reduced) rotationTarget.current.y+=delta*.12; group.current.rotation.y += (rotationTarget.current.y-group.current.rotation.y)*Math.min(1,delta*8)}});
 const steps=Array.from({length:10},(_,i)=>({z:2.55-i*.29,y:.12+i*.22}));
 return <group ref={group} position={[0,-.7,0]}>
  {/* The long, separate wings form an L around a courtyard. */}
  <Box position={[0,-.13,0]} size={[8.5,.24,7.2]} color="#bdcba9"/>
  <Box position={[0,.015,.2]} size={[6.4,.05,5.5]} color="#dce1ce"/>
  <Box position={[0,1,-2.1]} size={[6.9,2,1.55]} color="#f6f5eb"/>
  <Box position={[-2.675,1,.43]} size={[1.55,2,3.5]} color="#f2f2e7"/>
  <Box position={[0,2.04,-2.1]} size={[7.1,.15,1.78]} color="#65806a"/>
  <Box position={[-2.675,2.04,.43]} size={[1.78,.15,3.65]} color="#65806a"/>
  {/* Doors sit slightly in front of the inner wall surfaces, with space between them. */}
  {[-1.3,.4,2.1].map(x=><Door key={'back'+x} position={[x,0,-1.29]}/>)}
  {[-.55,.6,1.72].map(z=><Door key={'side'+z} position={[-1.87,0,z]} side/>)}
  <HouseSign/>
  <Box position={[1.7,2.82,-2.1]} size={[1.85,1.5,1.42]} color="#f8f7ee"/>
  <Door position={[1.7,2.08,-1.355]}/>
  <TiledRoof/>
  {/* The exterior staircase reaches a landing in front of the upper door. */}
  <Box position={[2.25,2.1,-1.06]} size={[1.95,.16,.72]} color="#d8d8c8"/>
  {steps.map((step,i)=><Box key={i} position={[3.13,step.y,step.z]} size={[.64,.22,.31]} color="#d7d8c8"/>)}
  <Box position={[3.13,2.22,-.38]} size={[.68,.16,1.05]} color="#d7d8c8"/>
  <Box position={[1.32,2.51,-.69]} size={[.08,.75,.08]} color="#6e8a71"/>
  <Box position={[2.48,2.51,-.69]} size={[.08,.75,.08]} color="#6e8a71"/>
  <Box position={[1.9,2.85,-.69]} size={[1.24,.07,.07]} color="#6e8a71"/>
  <Box position={[3.49,1.62,1.24]} size={[.06,.06,3.48]} rotation={[.65,0,0]} color="#6e8a71"/>
  {[0,3,6,9].map(i=><Box key={'rail'+i} position={[3.49,steps[i].y+.4,steps[i].z]} size={[.06,.8,.06]} color="#6e8a71"/>)}
  <Box position={[-3.65,.28,2.65]} size={[.15,.55,.15]} color="#896d53"/>
  <mesh position={[-3.65,.83,2.65]} castShadow><sphereGeometry args={[.45,9,7]}/><meshStandardMaterial color="#9eb77d"/></mesh>
  <Box position={[1.3,.13,2.25]} size={[.4,.25,.4]} color="#c4a485"/>
  <mesh position={[1.3,.44,2.25]}><sphereGeometry args={[.29,8,6]}/><meshStandardMaterial color="#789b6f"/></mesh>
 </group>
}
class HouseBoundary extends React.Component {constructor(p){super(p);this.state={failed:false}} static getDerivedStateFromError(){return {failed:true}} render(){return this.state.failed?<div className="house-fallback"><Home size={110}/><span>RumahIjo</span></div>:this.props.children}}
function HouseViewer(){const target=useRef({y:0,dragging:false}); const last=useRef(0); const velocity=useRef(0); const [reduced,setReduced]=useState(false);
 useEffect(()=>{const m=window.matchMedia('(prefers-reduced-motion: reduce)');const update=()=>setReduced(m.matches);update();m.addEventListener('change',update);return()=>m.removeEventListener('change',update)},[]);
 function down(e){e.currentTarget.setPointerCapture(e.pointerId);target.current.dragging=true;last.current=e.clientX;velocity.current=0;gsap.killTweensOf(target.current)}
 function move(e){if(!target.current.dragging)return;let dx=e.clientX-last.current;last.current=e.clientX;velocity.current=dx*.012;target.current.y+=velocity.current}
 function up(){if(!target.current.dragging)return;target.current.dragging=false;gsap.to(target.current,{y:target.current.y+velocity.current*8,duration:1.1,ease:'power3.out'})}
 return <div className="viewer" onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up} role="img" aria-label="Ilustrasi rumah kost berbentuk L dengan enam pintu di bawah, satu pintu di atas, dan tangga luar. Geser untuk memutar"><HouseBoundary><Canvas shadows dpr={[1,1.5]} camera={{position:[10,9,11],fov:38}} gl={{antialias:true,alpha:true}}><ambientLight intensity={1.8}/><directionalLight position={[5,9,5]} intensity={2.4} castShadow/><Suspense fallback={null}><House rotationTarget={target} reduced={reduced}/></Suspense></Canvas></HouseBoundary></div>
}
function TypingHeading(){
 const full='Ruang yang pas untuk ceritamu.'; const split='Ruang yang pas'.length;
 const ref=useRef(null); const [visible,setVisible]=useState(false); const [count,setCount]=useState(0); const [reduced,setReduced]=useState(false);
 useEffect(()=>{const media=window.matchMedia('(prefers-reduced-motion: reduce)');const update=()=>setReduced(media.matches);update();media.addEventListener('change',update);return()=>media.removeEventListener('change',update)},[]);
 useEffect(()=>{const observer=new IntersectionObserver(([entry])=>setVisible(entry.isIntersecting),{threshold:.25});if(ref.current)observer.observe(ref.current);return()=>observer.disconnect()},[]);
 useEffect(()=>{if(!visible||reduced)return;let timer;let current=0;setCount(0);const tick=()=>{current++;setCount(current);timer=setTimeout(current>=full.length?()=>{current=0;setCount(0);timer=setTimeout(tick,400)}:tick,current>=full.length?1900:85)};timer=setTimeout(tick,250);return()=>clearTimeout(timer)},[visible,reduced]);
 const amount=reduced?full.length:count;
 return <h2 ref={ref} className="typing-heading" aria-label={full}><span className="typing-reserve" aria-hidden="true">Ruang yang pas<br/>untuk ceritamu.</span><span className="typing-text" aria-hidden="true">{full.slice(0,Math.min(amount,split))}<br/>{full.slice(split,Math.min(amount,split+7))}<span>{full.slice(split+7,amount)}</span><i className="typing-cursor"/></span></h2>
}
function RoomArtwork({type}){
 return <div className={'room-art '+type.replace(' ','').toLowerCase()} aria-hidden="true"><div className="room-floor"><div className="room-wall back-wall"><div className="room-window"><i/><i/></div><div className="wall-art"/></div><div className="room-wall side-wall"/><div className="room-rug"/><div className="room-bed"><div className="bed-base"/><div className="bed-cover"/><div className="bed-pillow"/></div><div className="room-table"><i/></div><div className="room-plant"><i/></div></div></div>
}
function App(){const [nav,setNav]=useState(false),[active,setActive]=useState('kostku'),[filter,setFilter]=useState('Semua'),[roomFilter,setRoomFilter]=useState('Semua'),[message,setMessage]=useState(''),[toast,setToast]=useState('');
 useEffect(()=>{let observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting)setActive(e.target.id)}),{rootMargin:'-25% 0px -60% 0px'});document.querySelectorAll('main section[id]').forEach(s=>observer.observe(s));return()=>observer.disconnect()},[]);
 useEffect(()=>{if(!toast)return;let t=setTimeout(()=>setToast(''),2500);return()=>clearTimeout(t)},[toast]);
 const links=[['kostku','Kostku'],['property','Property'],['menu','Menu'],['contact','Contact']];
 function selectType(type){setMessage(`Halo RumahIjo, saya ingin bertanya tentang KamarIjoku ${type}.`);document.querySelector('#contact').scrollIntoView({behavior:'smooth'});}
 function selectRoom(id){setMessage(`Halo RumahIjo, saya ingin bertanya tentang ketersediaan kamar ${id}.`);document.querySelector('#contact').scrollIntoView({behavior:'smooth'});}
 function submit(e){e.preventDefault();setToast('Nomor WhatsApp belum tersedia. Hubungi pengelola setelah informasi kontak dilengkapi.')}
 return <><header className="nav-wrap"><nav className="nav"><a className="brand" href="#kostku"><span className="brand-icon"><Home size={27}/></span>Rumah<span>Ijo.</span></a><div className={'nav-links '+(nav?'open':'')}>{links.map(([id,label])=><a key={id} className={active===id?'active':''} href={'#'+id} onClick={()=>setNav(false)}>{label}</a>)}<div className="mobile-auth"><button onClick={()=>setToast('Login segera hadir')}>Login</button><button onClick={()=>setToast('Sign in segera hadir')}>Sign in</button></div></div><div className="auth"><button onClick={()=>setToast('Login segera hadir')}>Login</button><button className="sign" onClick={()=>setToast('Sign in segera hadir')}>Sign in <ArrowUpRight size={15}/></button></div><button className="hamburger" aria-label={nav?'Tutup menu':'Buka menu'} aria-expanded={nav} onClick={()=>setNav(!nav)}>{nav?<X/>:<MenuIcon/>}</button></nav></header>
 <main><section id="kostku" className="hero-section"><div className="hero-grid"><div className="hero-copy"><div className="eyebrow"><span className="small-dot"/> TEMPAT PULANG YANG NYAMAN</div><h1>Kost nyaman,<br/><span>rasa rumah</span><br/>sendiri.</h1><p>Ruang untuk beristirahat, bertumbuh, dan menjalani hari dengan lebih tenang.</p><a className="primary" href="#property">Jelajahi kost <ArrowUpRight size={18}/></a><div className="hero-bottom"><div className="round-mark"><Leaf size={21}/></div><div><strong>Ruang yang terasa pas.</strong><small>Untuk cerita barumu.</small></div><span className="hero-index">01 <em>/ 04</em></span></div></div><div className="hero-visual"><div className="visual-top"><span>RUMAHIJO / KOSTKU</span><span>EST. FOR LIVING <ArrowUpRight size={14}/></span></div><div className="visual-heading"><div><span className="visual-label">A LITTLE PLACE TO CALL HOME</span><h2>Temukan tempat<br/>yang terasa <i>tepat.</i></h2></div><div className="tags"><span>Kost</span><span>Rent</span><span>Home</span></div></div><HouseViewer/><div className="visual-bottom"><div className="drag-icon"><MoveHorizontal size={20}/></div><span>Geser untuk memutar rumah</span><span className="visual-number">01 <small>/ 01</small></span></div></div></div><div className="marquee" aria-label="For Rent"><div className="marquee-track" aria-hidden="true">{[0,1].map(i=><div className="marquee-group" key={i}>{Array.from({length:4},(_,j)=><React.Fragment key={j}><span>For Rent</span><Sparkle size={32}/></React.Fragment>)}</div>)}</div></div></section>
 <section id="property" className="property section"><div className="section-top"><div><span className="section-label">KENALAN DENGAN RUMAHIJO</span><TypingHeading/></div><p>Setiap orang punya cara sendiri untuk merasa di rumah. Temukan ruang yang sesuai dengan kebutuhanmu.</p></div><div className="section-row"><div><span className="mini-heading">PILIHAN KAMAR</span></div><div className="filters" aria-label="Filter properti">{['Semua','Type A','Type B','Type C'].map(f=><button className={filter===f?'selected':''} onClick={()=>setFilter(f)} key={f}>{f}</button>)}</div></div><div className="property-grid">{rooms.filter(r=>filter==='Semua'||r.type===filter).map((r,i)=><article className="property-card" key={r.id}><div className={'property-image image-'+r.id}><RoomArtwork type={r.type}/><span className="card-type">{r.type}</span><span className="card-count">{r.id} / 03</span></div><div className="property-info"><div className="property-title"><div><span>GAMBAR KAMAR</span><h3>{r.name}</h3></div><button onClick={()=>selectType(r.type)} aria-label={'Tanyakan '+r.name+' '+r.type}><ArrowUpRight size={20}/></button></div><div className="feature-list">{r.features.map(f=><span key={f}>{f}</span>)}</div><div className="price"><span>Harga sewa</span><strong>{r.price}</strong></div></div></article>)}</div></section>
 <section id="menu" className="menu-section section"><div className="menu-intro"><div><span className="section-label">PILIH CARA TINGGALMU</span><h2>Lebih fleksibel,<br/><span>lebih nyaman.</span></h2></div><p>Pilih durasi sewa yang paling cocok untuk rencanamu.</p></div><div className="plans">{[['01','Harian','Untuk kamu yang ingin tinggal lebih singkat.','Rp95.000 / hari'],['02','Bulanan','Pilihan fleksibel untuk tinggal lebih lama.','Rp650.000–Rp800.000 / bulan'],['03','Tahunan','Pilihan untuk rencana yang lebih panjang.','Rp6.000.000–Rp7.500.000 / tahun']].map(([n,title,desc,price])=><div className="plan" key={n}><span className="plan-num">{n} / 03</span><h3>{title}</h3><p>{desc}</p><div className="plan-bottom"><span>{price}</span><ArrowUpRight size={20}/></div></div>)}</div><div className="availability"><div className="availability-head"><div><span className="section-label">KETERSEDIAAN KAMAR</span><h2>Cari ruangmu.</h2><p>Ketersediaan dapat berubah. Hubungi pengelola untuk memastikan.</p></div><div className="room-stats"><div><strong>{inventory.filter(x=>x.status==='Kosong').length.toString().padStart(2,'0')}</strong><span><i className="status-dot empty"/> Kosong</span></div><div><strong>{inventory.filter(x=>x.status==='Terisi').length.toString().padStart(2,'0')}</strong><span><i className="status-dot occupied"/> Terisi</span></div></div></div><div className="room-toolbar"><span>NOMOR KAMAR</span><div className="filters">{['Semua','Kosong','Terisi'].map(f=><button key={f} className={roomFilter===f?'selected':''} onClick={()=>setRoomFilter(f)}>{f}</button>)}</div></div><div className="rooms">{inventory.filter(r=>roomFilter==='Semua'||r.status===roomFilter).map(r=><button key={r.id} className={'room '+(r.status==='Terisi'?'taken':'')} disabled={r.status==='Terisi'} onClick={()=>selectRoom(r.id)}><span>{r.id}</span><small><i className={'status-dot '+(r.status==='Kosong'?'empty':'occupied')}/>{r.status}</small></button>)}</div><p className="room-hint">Pilih kamar kosong untuk menanyakan detailnya. <ArrowRight size={15}/></p></div></section>
 <section id="contact" className="contact-section section"><div className="contact-copy"><span className="section-label">MARI BERKENALAN</span><h2>Mulai cerita<br/>barumu <span>di sini.</span></h2><p>Punya pertanyaan tentang RumahIjo? Tinggalkan pesan dan mari temukan ruang yang tepat untukmu.</p><div className="contact-details"><div><span>WHATSAPP</span><strong>Nomor belum tersedia</strong></div><div><span>LOKASI</span><strong>Alamat akan diinformasikan</strong></div><div><span>EMAIL & JAM RESPONS</span><strong>Informasi menyusul</strong></div></div></div><form className="contact-form" onSubmit={submit}><div className="form-title"><div><span className="section-label">KIRIM PESAN</span><h3>Ada yang ingin ditanyakan?</h3></div><div className="form-icon"><MessageCircle size={21}/></div></div><label>Nama<input required name="nama" placeholder="Nama lengkapmu"/></label><label>No. HP<input required name="telepon" type="tel" placeholder="Nomor yang bisa dihubungi"/></label><label>Pesan<textarea required name="pesan" rows="4" value={message} onChange={e=>setMessage(e.target.value)} placeholder="Ceritakan kebutuhanmu di sini"/></label><button className="primary form-button" type="submit">Chat via WhatsApp <ArrowUpRight size={18}/></button><small>Pengiriman tersedia setelah nomor WhatsApp pengelola ditambahkan.</small></form></section></main>
 <footer><div className="footer-top"><div><a className="brand" href="#kostku"><span className="brand-icon"><Home size={27}/></span>Rumah<span>Ijo.</span></a><p>Tempat pulang untuk cerita yang baru.</p><a className="footer-map" href="https://www.google.com/maps/search/?api=1&amp;query=-2.95780556%2C104.73394444" target="_blank" rel="noopener noreferrer">Lihat lokasi kost di Google Maps <ArrowUpRight size={14}/></a><small className="map-note">KM 4,5, Palembang</small></div><div className="footer-links">{links.map(([id,label])=><a href={'#'+id} key={id}>{label}</a>)}</div><a href="#kostku" className="back-top" aria-label="Kembali ke atas"><ArrowUpRight/></a></div><div className="footer-bottom"><span>© 2026 RumahIjo. Semua hak dilindungi.</span><span>MADE FOR FEELING AT HOME</span></div></footer>{toast&&<div role="status" className="toast">{toast}</div>}</>
}

createRoot(document.getElementById('root')).render(<App/>);
