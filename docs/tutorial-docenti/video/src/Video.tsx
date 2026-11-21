import React from 'react';
import { AbsoluteFill, Img, Sequence, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import dims from './dims.json';
import { FPS, scenes, sceneFrames, type Scene, type Shot } from './scenes';

// Colori neutri: il video vale per tutte le academy.
const C = {
  bg: '#F3F4F7',
  ink: '#0B0F19',
  muted: '#5B6372',
  accent: '#2F5BFF',
  mark: '#FFB400',
  card: '#FFFFFF',
};
const FONT = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif";

// Zona dove si mostra la schermata.
const AREA = { x: 160, y: 150, w: 1600, h: 700 };

const dim = (name: string) => (dims as Record<string, { w: number; h: number }>)[name] ?? { w: 1440, h: 900 };

const ShotView: React.FC<{ shot: Shot; frames: number }> = ({ shot, frames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const { w, h } = dim(shot.img);
  const s0 = Math.min(AREA.w / w, AREA.h / h, 2.2);
  const iw = w * s0;
  const ih = h * s0;
  const left = (AREA.w - iw) / 2;
  const top = (AREA.h - ih) / 2;

  const enter = spring({ frame, fps, config: { damping: 200 }, durationInFrames: 18 });
  const fadeOut = interpolate(frame, [frames - 8, frames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  let k = 1;
  let tx = 0;
  let ty = 0;
  let markOpacity = 0;
  if (shot.focus) {
    const [fx, fy, fw, fh] = shot.focus;
    // Lo zoom non supera mai 3 volte la dimensione reale, così le immagini restano nitide.
    const zoom = Math.max(1, Math.min(2.4, 3 / s0, (AREA.w * 0.78) / (fw * s0), (AREA.h * 0.78) / (fh * s0)));
    const p = spring({ frame: frame - 14, fps, config: { damping: 200 }, durationInFrames: 36 });
    k = 1 + (zoom - 1) * p;
    const cx = (fx + fw / 2) * s0;
    const cy = (fy + fh / 2) * s0;
    tx = AREA.w / 2 - left - cx * k;
    ty = AREA.h / 2 - top - cy * k;
    // Non si scopre mai il bordo bianco: se l'immagine ingrandita riempie la zona, la si tiene dentro; altrimenti resta al centro.
    const sw = iw * k;
    const sh = ih * k;
    tx = sw >= AREA.w ? Math.min(-left, Math.max(AREA.w - left - sw, tx)) : (AREA.w - sw) / 2 - left;
    ty = sh >= AREA.h ? Math.min(-top, Math.max(AREA.h - top - sh, ty)) : (AREA.h - sh) / 2 - top;
    // Il centro dell'immagine resta fermo mentre non c'è zoom, poi si sposta sul punto da guardare.
    tx = tx * p;
    ty = ty * p;
    markOpacity = interpolate(frame, [20, 34], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  }
  const pulse = 0.65 + 0.35 * Math.sin(frame / 7);

  return (
    <div
      style={{
        position: 'absolute',
        left: AREA.x,
        top: AREA.y,
        width: AREA.w,
        height: AREA.h,
        overflow: 'hidden',
        borderRadius: 22,
        background: '#E6E8EC',
        boxShadow: '0 30px 80px rgba(11,15,25,.18), 0 0 0 1px rgba(11,15,25,.06)',
        opacity: enter * fadeOut,
        transform: `scale(${0.96 + 0.04 * enter})`,
      }}
    >
      <div style={{ position: 'absolute', left, top, width: iw, height: ih, transformOrigin: '0 0', transform: `translate(${tx}px, ${ty}px) scale(${k})` }}>
        <Img src={staticFile('img/' + shot.img)} style={{ width: iw, height: ih, display: 'block' }} />
        {shot.focus ? (
          <div
            style={{
              position: 'absolute',
              left: shot.focus[0] * s0 - 6,
              top: shot.focus[1] * s0 - 6,
              width: shot.focus[2] * s0 + 12,
              height: shot.focus[3] * s0 + 12,
              border: `${5 / k}px solid ${C.mark}`,
              borderRadius: 12 / k,
              boxShadow: `0 0 0 ${2 / k}px rgba(255,255,255,.9), 0 0 ${24 * pulse}px rgba(255,180,0,.75)`,
              opacity: markOpacity,
            }}
          />
        ) : null}
      </div>
    </div>
  );
};

const Caption: React.FC<{ text: string; frames: number }> = ({ text, frames }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({ frame: frame - 6, fps, config: { damping: 200 }, durationInFrames: 20 });
  const out = interpolate(frame, [frames - 8, frames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <div
      style={{
        position: 'absolute',
        left: 160,
        width: 1600,
        top: 880,
        padding: '22px 34px',
        borderRadius: 20,
        background: C.ink,
        color: '#fff',
        fontFamily: FONT,
        fontSize: 40,
        lineHeight: 1.25,
        fontWeight: 600,
        opacity: enter * out,
        transform: `translateY(${(1 - enter) * 24}px)`,
        textAlign: 'center',
      }}
    >
      {text}
    </div>
  );
};

const Header: React.FC<{ step: number; total: number; title: string }> = ({ step, total, title }) => (
  <div style={{ position: 'absolute', left: 160, top: 52, width: 1600, display: 'flex', alignItems: 'center', gap: 22, fontFamily: FONT }}>
    <div style={{ padding: '8px 22px', borderRadius: 999, background: C.accent, color: '#fff', fontSize: 26, fontWeight: 700 }}>
      Passo {step} di {total}
    </div>
    <div style={{ fontSize: 54, fontWeight: 800, letterSpacing: '-0.02em', color: C.ink }}>{title}</div>
  </div>
);

const StepScene: React.FC<{ scene: Scene; step: number; total: number }> = ({ scene, step, total }) => {
  let start = 0;
  return (
    <AbsoluteFill style={{ background: C.bg }}>
      <Header step={step} total={total} title={scene.title} />
      {scene.shots.map((shot, i) => {
        const frames = Math.round(shot.seconds * FPS);
        const from = start;
        start += frames;
        return (
          <Sequence key={i} from={from} durationInFrames={frames}>
            <ShotView shot={shot} frames={frames} />
            <Caption text={shot.caption ?? scene.caption} frames={frames} />
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};

const TitleScene: React.FC<{ scene: Scene; dark: boolean }> = ({ scene, dark }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({ frame, fps, config: { damping: 200 }, durationInFrames: 24 });
  const frames = sceneFrames(scene);
  const out = interpolate(frame, [frames - 10, frames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  return (
    <AbsoluteFill style={{ background: dark ? C.ink : C.accent, alignItems: 'center', justifyContent: 'center', fontFamily: FONT, opacity: out }}>
      <div style={{ width: 1400, textAlign: 'center', transform: `translateY(${(1 - enter) * 40}px)`, opacity: enter }}>
        <div style={{ fontSize: 96, fontWeight: 800, letterSpacing: '-0.03em', lineHeight: 1.05, color: '#fff' }}>{scene.title}</div>
        <div style={{ marginTop: 34, fontSize: 44, fontWeight: 500, color: 'rgba(255,255,255,.82)' }}>{scene.caption}</div>
      </div>
    </AbsoluteFill>
  );
};

export const Tutorial: React.FC = () => {
  const total = scenes.filter((s) => s.kind === 'steps').length;
  let start = 0;
  let step = 0;
  return (
    <AbsoluteFill>
      {scenes.map((scene, i) => {
        const frames = sceneFrames(scene);
        const from = start;
        start += frames;
        if (scene.kind === 'steps') step += 1;
        return (
          <Sequence key={i} from={from} durationInFrames={frames}>
            {scene.kind === 'steps' ? <StepScene scene={scene} step={step} total={total} /> : <TitleScene scene={scene} dark={scene.kind === 'outro'} />}
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};
