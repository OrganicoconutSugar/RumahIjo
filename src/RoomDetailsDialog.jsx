import React, { useEffect, useRef, useState } from 'react';
import { ArrowRight, ArrowUpRight, Home, X } from './Icons3D';
import { buildRoomInquiry, requestableFurniture } from './landingData';

export default function RoomDetailsDialog({ room, onClose, onContact }) {
  const dialogRef = useRef(null);
  const [photoIndex, setPhotoIndex] = useState(0);
  const [requestedItems, setRequestedItems] = useState([]);
  const [requestNote, setRequestNote] = useState('');
  const [failedPhotos, setFailedPhotos] = useState([]);
  const photos = Array.from({ length: 4 }, (_, index) => room.photos?.[index] ?? null);
  const activePhoto = failedPhotos.includes(photoIndex) ? null : photos[photoIndex];
  const isCustomRoom = room.type === 'Isian';

  useEffect(() => {
    const dialog = dialogRef.current;
    const opener = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    const previousPadding = document.body.style.paddingRight;
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;

    document.body.style.overflow = 'hidden';
    if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;
    dialog.showModal();

    return () => {
      dialog.close();
      document.body.style.overflow = previousOverflow;
      document.body.style.paddingRight = previousPadding;
      if (opener instanceof HTMLElement && opener.isConnected) opener.focus({ preventScroll: true });
    };
  }, []);

  function changePhoto(offset) {
    setPhotoIndex(index => (index + offset + photos.length) % photos.length);
  }

  function toggleItem(item) {
    setRequestedItems(items => items.includes(item) ? items.filter(value => value !== item) : [...items, item]);
  }

  function handleBackdrop(event) {
    if (event.target !== event.currentTarget) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) onClose();
  }

  function trapFocus(event) {
    if (event.key !== 'Tab') return;
    const controls = [...dialogRef.current.querySelectorAll('button, input, textarea, a[href], [tabindex="0"]')]
      .filter(control => !control.disabled && control.getClientRects().length > 0);
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || !dialogRef.current.contains(document.activeElement))) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first?.focus();
    }
  }

  return (
    <dialog ref={dialogRef} className="room-dialog" aria-labelledby="room-dialog-title" onCancel={event => { event.preventDefault(); onClose(); }} onClick={handleBackdrop} onKeyDown={trapFocus}>
      <div className="room-dialog-header">
        <div><span className="section-label">DETAIL KAMAR</span><h2 id="room-dialog-title">{room.name} <span>{room.type}</span></h2></div>
        <button type="button" className="dialog-close" aria-label="Tutup detail kamar" onClick={onClose} autoFocus><X size={24}/></button>
      </div>
      <div className="room-dialog-layout">
        <div className="room-gallery" role="region" aria-label="Galeri empat foto kamar" aria-roledescription="carousel" onKeyDown={event => {
          if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); changePhoto(event.key === 'ArrowLeft' ? -1 : 1); }
        }}>
          <div className="gallery-stage" role="group" aria-roledescription="slide" aria-label={`Foto ${photoIndex + 1} dari 4`}>
            {activePhoto ? <img src={activePhoto.src} alt={activePhoto.alt || `Foto asli ${room.name} ${room.type}, ${photoIndex + 1}`} className="gallery-photo" onError={() => setFailedPhotos(indices => [...indices, photoIndex])}/> : <div className="gallery-placeholder"><Home size={100}/><strong>Foto {String(photoIndex + 1).padStart(2, '0')}</strong><span>Foto asli kamar belum ditambahkan.</span></div>}
            <div className="gallery-navigation">
              <button type="button" onClick={() => changePhoto(-1)} aria-label="Foto sebelumnya"><span className="reverse-arrow"><ArrowRight size={20}/></span></button>
              <span aria-live="polite" aria-atomic="true">{String(photoIndex + 1).padStart(2, '0')} <small>/ 04</small></span>
              <button type="button" onClick={() => changePhoto(1)} aria-label="Foto berikutnya"><ArrowRight size={20}/></button>
            </div>
          </div>
          <div className="gallery-thumbnails" aria-label="Pilih foto kamar">
            {photos.map((photo, index) => <button type="button" key={index} onClick={() => setPhotoIndex(index)} aria-label={`Lihat foto ${index + 1}`} aria-pressed={photoIndex === index}>
              {photo && !failedPhotos.includes(index) ? <img className="gallery-thumb-photo" src={photo.src} alt="" onError={() => setFailedPhotos(indices => [...indices, index])}/> : <><Home size={28}/><span>{String(index + 1).padStart(2, '0')}</span></>}
            </button>)}
          </div>
        </div>
        <div className="room-dialog-info">
          <div className="dialog-price"><span>Harga sewa</span><strong>{room.price}</strong></div>
          {room.type === 'Kosongan' && <p className="dialog-description">Kamar tanpa furnitur bawaan, siap diisi dengan barang milikmu sendiri.</p>}
          {isCustomRoom && <p className="dialog-description">Pilih barang yang ingin kamu minta untuk melengkapi kamar. Ketersediaan dan biaya akan dikonfirmasi pengelola.</p>}
          {room.features.length > 0 && <div className="dialog-furniture"><h3>Furnitur yang disediakan</h3><div className="feature-list">{room.features.map(item => <span key={item}>{item}</span>)}</div></div>}
          {isCustomRoom && <fieldset className="furniture-requests"><legend>Mau diisi apa?</legend><div className="furniture-options">{requestableFurniture.map(item => <label key={item}><input type="checkbox" checked={requestedItems.includes(item)} onChange={() => toggleItem(item)}/><span>{item}</span></label>)}</div><label className="request-note">Permintaan lainnya <span>(opsional)</span><textarea rows="2" maxLength={500} value={requestNote} onChange={event => setRequestNote(event.target.value)} placeholder="Tulis barang atau kebutuhan lainnya"/></label></fieldset>}
          <div className="dialog-utilities"><h3>Listrik & air PDAM</h3><p><strong>Listrik:</strong> token pribadi, dibayar terpisah dari biaya sewa.</p><p><strong>Air PDAM:</strong> hubungi pengelola untuk memastikan biaya dan ketentuannya.</p></div>
          <button type="button" className="primary dialog-contact" onClick={() => onContact(buildRoomInquiry(room, requestedItems, requestNote))}>Tanyakan via WhatsApp <ArrowUpRight size={19}/></button>
          <p className="dialog-contact-note">Pilihanmu akan dimasukkan ke pesan pada formulir kontak.</p>
        </div>
      </div>
    </dialog>
  );
}
