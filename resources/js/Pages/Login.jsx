import React, {useState} from 'react';
import {Head, useForm} from '@inertiajs/react';
import './portal.css';
import './login-redesign.css';

export default function Login() {
  const {data, setData, post, processing, errors} = useForm({email: '', password: '', remember: false});
  const [showPassword, setShowPassword] = useState(false);

  function submit(event) {
    event.preventDefault();
    post('/login');
  }

  return <main className="signin-page">
    <Head title="Login | RumahIjo"/>
    <div className="signin-shell">
      <section className="signin-welcome" aria-label="Selamat datang di RumahIjo">
        <div className="welcome-body">
          <p className="signin-eyebrow"><span/> PORTAL PENYEWA</p>
          <h2>Tempat pulang,<br/><span>di setiap cerita.</span></h2>
          <p className="welcome-description">Semua yang kamu butuhkan untuk tinggal dengan nyaman, ada di sini.</p>
          <div className="welcome-art"><img className="signin-logo-image" src="/icons/3d/rumahijo-login-logo.png" alt="Logo RumahIjo" width="1254" height="1254"/></div>
        </div>
      </section>
      <section className="signin-content">
        <div className="signin-top"><a href="/" className="signin-back"><span aria-hidden="true">←</span> Kembali ke beranda</a><span className="signin-top-label">PORTAL PENYEWA</span></div>
        <div className="signin-form-wrap">
          <p className="signin-eyebrow signin-form-eyebrow">SELAMAT DATANG KEMBALI</p>
          <h1>Masuk ke RumahIjo</h1>
          <form className="signin-form" onSubmit={submit}>
            <label htmlFor="signin-email">Email</label>
            <input id="signin-email" type="email" name="email" required autoComplete="username" placeholder="nama@email.com" value={data.email} onChange={event=>setData('email',event.target.value)} aria-invalid={!!errors.email} aria-describedby={errors.email?'signin-error':undefined}/>
            <label htmlFor="signin-password">Password</label>
            <div className="signin-password-field"><input id="signin-password" type={showPassword?'text':'password'} name="password" required autoComplete="current-password" placeholder="Masukkan password" value={data.password} onChange={event=>setData('password',event.target.value)} aria-invalid={!!errors.password}/><button type="button" className="signin-password-toggle" onClick={()=>setShowPassword(!showPassword)} aria-pressed={showPassword} aria-label={showPassword?'Sembunyikan password':'Tampilkan password'}>{showPassword?'Sembunyikan':'Lihat'}</button></div>
            {(errors.email||errors.password)&&<p id="signin-error" className="signin-error" role="alert">{errors.email||errors.password}</p>}
            <label className="signin-remember"><input type="checkbox" checked={data.remember} onChange={event=>setData('remember',event.target.checked)}/><span>Ingat saya di perangkat ini</span></label>
            <button className="signin-submit" type="submit" disabled={processing}><span>{processing?'Sedang masuk...':'Masuk'}</span><span aria-hidden="true">↗</span></button>
          </form>
          <div className="signin-help"><span className="signin-icon signin-icon-chat" aria-hidden="true"/><p>Akun dibuat oleh pemilik kost.<br/><strong>Belum punya akun? <a className="signin-owner-link" href="https://wa.me/6285117473635" target="_blank" rel="noopener noreferrer">Hubungi Pemilik</a>.</strong></p></div>
        </div>
      </section>
    </div>
  </main>;
}
