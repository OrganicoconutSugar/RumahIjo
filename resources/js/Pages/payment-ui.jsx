import React from 'react';
export const money = value => 'Rp'+Number(value||0).toLocaleString('id-ID');
export const dateLabel = value => value ? new Date(value.slice(0,10)+'T12:00:00Z').toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}) : '—';
export const periodLabel = value => value ? new Date(value.slice(0,10)+'T12:00:00Z').toLocaleDateString('id-ID',{month:'long',year:'numeric',timeZone:'UTC'}) : '—';
export const statusLabels={unpaid:'Belum dibayar',pending:'Menunggu verifikasi',paid:'Lunas',overdue:'Terlambat',cancelled:'Dibatalkan',failed:'Gagal / ditolak',expired:'Kedaluwarsa'};
export function PaymentIcon({kind='tagihan',className=''}) {return <span className={`icon-3d icon-${kind} ${className}`} aria-hidden="true"/>;}
export function PaymentStatus({status}) {return <span className={'pay-status '+status}><i/>{statusLabels[status]||status}</span>;}
