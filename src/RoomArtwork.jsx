import React from 'react';
import './room-art.css';
export default function RoomArtwork({type}){
 return <div className={'room-art '+type.replace(' ','').toLowerCase()} aria-hidden="true"><div className="room-floor"><div className="room-wall back-wall"><div className="room-window"><i/><i/></div><div className="wall-art"/></div><div className="room-wall side-wall"/><div className="room-rug"/><div className="room-bed"><div className="bed-base"/><div className="bed-cover"/><div className="bed-pillow"/></div><div className="room-table"><i/></div><div className="room-plant"><i/></div></div></div>
}
