import { Composition } from 'remotion';
import { Tutorial } from './Video';
import { FPS, totalFrames } from './scenes';

export const Root = () => (
  <Composition id="Tutorial" component={Tutorial} durationInFrames={totalFrames} fps={FPS} width={1920} height={1080} />
);
