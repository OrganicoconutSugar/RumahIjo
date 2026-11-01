import React,{useEffect,useState} from 'react';
export default function TypingGreeting({name}) {
 const text=`Halo, ${name}`;const [count,setCount]=useState(text.length);const [reduced,setReduced]=useState(true);
 useEffect(()=>{const media=window.matchMedia('(prefers-reduced-motion: reduce)');const update=()=>setReduced(media.matches);update();media.addEventListener('change',update);return()=>media.removeEventListener('change',update);},[]);
 useEffect(()=>{if(reduced){setCount(text.length);return;}setCount(0);let n=0;const timer=setInterval(()=>{n++;setCount(n);if(n>=text.length)clearInterval(timer);},100);return()=>clearInterval(timer);},[text,reduced]);
 return <span className="typing-greeting" aria-label={text}><span className="greeting-reserve" aria-hidden="true">{text}</span><span className="greeting-text" aria-hidden="true">{text.slice(0,count)}{!reduced&&<i/>}</span></span>;
}
