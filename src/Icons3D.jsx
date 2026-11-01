import React, { useId } from 'react';
import houseIcon from './assets/house-soft-3d.png';
import chatIcon from './assets/chat-soft-3d.png';

// Each glyph has a solid extruded side and a shaded face. It has no surrounding disc or shadow.
function Icon({size=24, children, kind, ...props}) {
 const id=useId().replace(/:/g,'');
 return <svg className="icon-3d" width={size} height={size} viewBox="0 0 28 28" fill="none" aria-hidden="true" focusable="false" {...props}>
  <defs><linearGradient id={id} x1="4" y1="3" x2="23" y2="23" gradientUnits="userSpaceOnUse"><stop stopColor="var(--icon-light,#b9d2af)"/><stop offset="1" stopColor="var(--icon-face,#5e8f67)"/></linearGradient></defs>
  {children({face:`url(#${id})`,side:'var(--icon-side,#365e40)',detail:'var(--icon-detail,#e6f0e1)'})}
 </svg>
}
function Shape({d,face,side}){return <><path d={d} fill={side} transform="translate(1.1 2)"/><path d={d} fill={face}/></>}
export function ArrowUpRight(props){return <Icon {...props}>{c=><Shape {...c} d="M5 18.6 16 7.6H9.2V4.5H21v11.8h-3.1V9.7L7.1 20.7z"/>}</Icon>}
export function ArrowRight(props){return <Icon {...props}>{c=><Shape {...c} d="M4 11.3h13L12.7 7l2.2-2.2 8 8-8 8-2.2-2.2 4.3-4.3H4z"/>}</Icon>}
export function MoveHorizontal(props){return <Icon {...props}>{c=><Shape {...c} d="m8 5 2.1 2.1-3.6 3.6h14l-3.6-3.6L19 5l7 7-7 7-2.1-2.1 3.6-3.6h-14l3.6 3.6L8 19l-7-7z"/>}</Icon>}
export function Home({size=24, ...props}){return <img className="icon-3d asset-3d" src={houseIcon} width={size} height={size} alt="" aria-hidden="true" {...props}/>}
export function Leaf(props){return <Icon {...props}>{c=><><Shape {...c} d="M24.3 3.5c-10.1 0-19 2.7-19 11.4 0 3 1.2 5.5 3.4 6.8C13.8 25 23.7 21.1 24.3 3.5Z"/><path d="M5.4 23c3.3-5.4 7.4-9.3 14.3-13.2" stroke={c.detail} strokeWidth="1.7" strokeLinecap="round"/></>}</Icon>}
export function MessageCircle({size=24, ...props}){return <img className="icon-3d asset-3d" src={chatIcon} width={size} height={size} alt="" aria-hidden="true" {...props}/>}
export function Menu(props){return <Icon {...props}>{c=><>{[5,11,17].map(y=><Shape key={y} {...c} d={`M4 ${y}h19v3H4z`}/>)}</>}</Icon>}
export function X(props){return <Icon {...props}>{c=><Shape {...c} d="m6 3.5 7.4 7.4 7.4-7.4 2.6 2.6-7.4 7.4 7.4 7.4-2.6 2.6-7.4-7.4L6 23.5l-2.6-2.6 7.4-7.4-7.4-7.4z"/>}</Icon>}
export function Sparkle(props){return <Icon {...props}>{c=><Shape {...c} d="M14 2.5 16 9l5.4-4-2.3 6.3 6.8.7-6.8 1.8 2.3 6.3-5.4-4L14 23l-2-6.9-5.4 4 2.3-6.3-6.8-1.8 6.8-.7-2.3-6.3L12 9z"/>}</Icon>}
