import { Renderer, Program, Mesh, Color, Triangle } from 'ogl';

const vertexShader = `
attribute vec2 uv;
attribute vec2 position;

varying vec2 vUv;

void main() {
  vUv = uv;
  gl_Position = vec4(position, 0, 1);
}
`;

const fragmentShader = `
precision highp float;

uniform float uTime;
uniform vec3 uResolution;
uniform vec2 uFocal;
uniform vec2 uRotation;
uniform float uStarSpeed;
uniform float uDensity;
uniform float uHueShift;
uniform float uSpeed;
uniform vec2 uMouse;
uniform float uGlowIntensity;
uniform float uSaturation;
uniform bool uMouseRepulsion;
uniform float uTwinkleIntensity;
uniform float uRotationSpeed;
uniform float uRepulsionStrength;
uniform float uMouseActiveFactor;
uniform float uAutoCenterRepulsion;
uniform bool uTransparent;
uniform float uLightMode;

varying vec2 vUv;

#define NUM_LAYER 4.0
#define STAR_COLOR_CUTOFF 0.2
#define MAT45 mat2(0.7071, -0.7071, 0.7071, 0.7071)
#define PERIOD 3.0

float Hash21(vec2 p) {
  p = fract(p * vec2(123.34, 456.21));
  p += dot(p, p + 45.32);
  return fract(p.x * p.y);
}

float tri(float x) {
  return abs(fract(x) * 2.0 - 1.0);
}

float tris(float x) {
  float t = fract(x);
  return 1.0 - smoothstep(0.0, 1.0, abs(2.0 * t - 1.0));
}

float trisn(float x) {
  float t = fract(x);
  return 2.0 * (1.0 - smoothstep(0.0, 1.0, abs(2.0 * t - 1.0))) - 1.0;
}

vec3 hsv2rgb(vec3 c) {
  vec4 K = vec4(1.0, 2.0 / 3.0, 1.0 / 3.0, 3.0);
  vec3 p = abs(fract(c.xxx + K.xyz) * 6.0 - K.www);
  return c.z * mix(K.xxx, clamp(p - K.xxx, 0.0, 1.0), c.y);
}

float Star(vec2 uv, float flare) {
  float d = length(uv);
  float m = (0.05 * uGlowIntensity) / d;
  float rays = smoothstep(0.0, 1.0, 1.0 - abs(uv.x * uv.y * 1000.0));
  m += rays * flare * uGlowIntensity;
  uv *= MAT45;
  rays = smoothstep(0.0, 1.0, 1.0 - abs(uv.x * uv.y * 1000.0));
  m += rays * 0.3 * flare * uGlowIntensity;
  m *= smoothstep(1.0, 0.2, d);
  return m;
}

vec3 StarLayer(vec2 uv) {
  vec3 col = vec3(0.0);

  vec2 gv = fract(uv) - 0.5;
  vec2 id = floor(uv);

  for (int y = -1; y <= 1; y++) {
    for (int x = -1; x <= 1; x++) {
      vec2 offset = vec2(float(x), float(y));
      vec2 si = id + vec2(float(x), float(y));
      float seed = Hash21(si);
      float size = min(fract(seed * 345.32), 0.75);
      float glossLocal = tri(uStarSpeed / (PERIOD * seed + 1.0));
      float flareSize = smoothstep(0.9, 1.0, size) * glossLocal;

      float red = smoothstep(STAR_COLOR_CUTOFF, 1.0, Hash21(si + 1.0)) + STAR_COLOR_CUTOFF;
      float blu = smoothstep(STAR_COLOR_CUTOFF, 1.0, Hash21(si + 3.0)) + STAR_COLOR_CUTOFF;
      float grn = min(red, blu) * seed;
      vec3 base = vec3(red, grn, blu);

      float hue = atan(base.g - base.r, base.b - base.r) / (2.0 * 3.14159) + 0.5;
      hue = fract(hue + uHueShift / 360.0);
      float sat = length(base - vec3(dot(base, vec3(0.299, 0.587, 0.114)))) * uSaturation;
      float val = max(max(base.r, base.g), base.b);
      base = hsv2rgb(vec3(hue, sat, val));

      vec2 pad = vec2(tris(seed * 34.0 + uTime * uSpeed / 10.0), tris(seed * 38.0 + uTime * uSpeed / 30.0)) - 0.5;

      float star = Star(gv - offset - pad, flareSize);
      vec3 color = base;

      float twinkle = trisn(uTime * uSpeed + seed * 6.2831) * 0.5 + 1.0;
      twinkle = mix(1.0, twinkle, uTwinkleIntensity);
      star *= twinkle;

      col += star * size * color;
    }
  }

  return col;
}

void main() {
  vec2 focalPx = uFocal * uResolution.xy;
  vec2 uv = (vUv * uResolution.xy - focalPx) / uResolution.y;

  vec2 mouseNorm = uMouse - vec2(0.5);

  /* Center + mouse repulsion digabung aditif (aslinya if/else: center
     mematikan mouse). Center menepi dari belakang card, mouse tetap jalan. */
  if (uAutoCenterRepulsion > 0.0) {
    vec2 centerUV = vec2(0.0, 0.0);
    float centerDist = length(uv - centerUV);
    vec2 centerPush = normalize(uv - centerUV) * (uAutoCenterRepulsion / (centerDist + 0.1));
    uv += centerPush * 0.05;
  }
  if (uMouseRepulsion) {
    vec2 mousePosUV = (uMouse * uResolution.xy - focalPx) / uResolution.y;
    float mouseDist = length(uv - mousePosUV);
    vec2 repulsion = normalize(uv - mousePosUV) * (uRepulsionStrength / (mouseDist + 0.1));
    uv += repulsion * 0.05 * uMouseActiveFactor;
  } else {
    vec2 mouseOffset = mouseNorm * 0.1 * uMouseActiveFactor;
    uv += mouseOffset;
  }

  float autoRotAngle = uTime * uRotationSpeed;
  mat2 autoRot = mat2(cos(autoRotAngle), -sin(autoRotAngle), sin(autoRotAngle), cos(autoRotAngle));
  uv = autoRot * uv;

  uv = mat2(uRotation.x, -uRotation.y, uRotation.y, uRotation.x) * uv;

  vec3 col = vec3(0.0);

  for (float i = 0.0; i < 1.0; i += 1.0 / NUM_LAYER) {
    float depth = fract(i + uStarSpeed * uSpeed);
    float scale = mix(20.0 * uDensity, 0.5 * uDensity, depth);
    float fade = depth * depth * smoothstep(1.0, 0.9, depth);
    col += StarLayer(uv * scale + i * 453.32) * fade;
  }

  if (uLightMode > 0.5) {
    float energy = max(max(col.r, col.g), col.b);
    float coverage = clamp(smoothstep(0.0, 0.42, energy) * 0.92, 0.0, 0.92);
    vec3 ink = clamp(col * 0.48, 0.0, 0.82);
    gl_FragColor = vec4(mix(vec3(1.0), ink, coverage), 1.0);
  } else if (uTransparent) {
    float alpha = length(col);
    alpha = smoothstep(0.0, 0.18, alpha);
    alpha = min(alpha, 1.0);
    gl_FragColor = vec4(col, alpha);
  } else {
    gl_FragColor = vec4(col, 1.0);
  }
}
`;

const defaults = {
    focal: [0.5, 0.5],
    rotation: [1.0, 0.0],
    starSpeed: 0.4,
    density: 1.8,
    hueShift: 210,
    disableAnimation: false,
    speed: 0.6,
    mouseInteraction: true,
    glowIntensity: 0.4,
    saturation: 0.4,
    mouseRepulsion: true,
    repulsionStrength: 0.4,
    twinkleIntensity: 0.35,
    rotationSpeed: 0.05,
    autoCenterRepulsion: 1.2,
    transparent: true,
    lightMode: false,
};

/* Vanilla port of the React Bits <Galaxy /> component (no React).
   Returns a destroy() cleanup. Safe to call once per container. */
export function initGalaxy(container, options = {}) {
    if (!container) return () => {};
    const o = { ...defaults, ...options };
    if (window.matchMedia('(pointer: coarse)').matches) o.density *= 0.7;

    const targetMousePos = { x: 0.5, y: 0.5 };
    const smoothMousePos = { x: 0.5, y: 0.5 };
    let targetMouseActive = 0.0;
    let smoothMouseActive = 0.0;

    /* dpr 1: fullscreen shader di retina 2x = 4x fragment (lag saat zoom-out).
       Bintang ber-glow tetap terlihat tajam di 1x. */
    const renderer = new Renderer({ alpha: o.transparent, premultipliedAlpha: false, dpr: 1 });
    const gl = renderer.gl;

    if (o.lightMode) {
        gl.clearColor(1, 1, 1, 1);
    } else if (o.transparent) {
        gl.enable(gl.BLEND);
        gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
        gl.clearColor(0, 0, 0, 0);
    } else {
        gl.clearColor(0, 0, 0, 1);
    }

    let program;
    const resize = () => {
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        if (program) {
            program.uniforms.uResolution.value = new Color(
                gl.canvas.width,
                gl.canvas.height,
                gl.canvas.width / gl.canvas.height
            );
        }
    };
    window.addEventListener('resize', resize);
    resize();

    program = new Program(gl, {
        vertex: vertexShader,
        fragment: fragmentShader,
        uniforms: {
            uTime: { value: 0 },
            uResolution: {
                value: new Color(gl.canvas.width, gl.canvas.height, gl.canvas.width / gl.canvas.height),
            },
            uFocal: { value: new Float32Array(o.focal) },
            uRotation: { value: new Float32Array(o.rotation) },
            uStarSpeed: { value: o.starSpeed },
            uDensity: { value: o.density },
            uHueShift: { value: o.hueShift },
            uSpeed: { value: o.speed },
            uMouse: { value: new Float32Array([smoothMousePos.x, smoothMousePos.y]) },
            uGlowIntensity: { value: o.glowIntensity },
            uSaturation: { value: o.saturation },
            uMouseRepulsion: { value: o.mouseRepulsion },
            uTwinkleIntensity: { value: o.twinkleIntensity },
            uRotationSpeed: { value: o.rotationSpeed },
            uRepulsionStrength: { value: o.repulsionStrength },
            uMouseActiveFactor: { value: 0.0 },
            uAutoCenterRepulsion: { value: o.autoCenterRepulsion },
            uTransparent: { value: o.transparent },
            uLightMode: { value: o.lightMode ? 1 : 0 },
        },
    });

    const mesh = new Mesh(gl, { geometry: new Triangle(gl), program });
    let animateId = 0;
    let lastT = -1;

    const update = (t) => {
        animateId = requestAnimationFrame(update);
        if (document.hidden) return; // tab tak terlihat: hemat GPU/baterai
        if (lastT < 0) lastT = t;
        /* Delta-time smoothing: respons konsisten di FPS berapa pun
           (lerp fixed per-frame melambat saat FPS turun). */
        const dt = Math.min((t - lastT) / 1000, 0.1);
        lastT = t;
        if (!o.disableAnimation) {
            program.uniforms.uTime.value = t * 0.001;
            program.uniforms.uStarSpeed.value = (t * 0.001 * o.starSpeed) / 10.0;
        }
        const kp = 1 - Math.exp(-8 * dt);
        const ka = 1 - Math.exp(-10 * dt);
        smoothMousePos.x += (targetMousePos.x - smoothMousePos.x) * kp;
        smoothMousePos.y += (targetMousePos.y - smoothMousePos.y) * kp;
        smoothMouseActive += (targetMouseActive - smoothMouseActive) * ka;
        program.uniforms.uMouse.value[0] = smoothMousePos.x;
        program.uniforms.uMouse.value[1] = smoothMousePos.y;
        program.uniforms.uMouseActiveFactor.value = smoothMouseActive;
        renderer.render({ scene: mesh });
    };
    animateId = requestAnimationFrame(update);
    container.appendChild(gl.canvas);

    const handleMouseMove = (e) => {
        const rect = container.getBoundingClientRect();
        targetMousePos.x = (e.clientX - rect.left) / rect.width;
        targetMousePos.y = 1.0 - (e.clientY - rect.top) / rect.height;
        targetMouseActive = 1.0;
    };
    const handleMouseLeave = () => {
        targetMouseActive = 0.0;
    };
    /* Listen on window: the container sits behind the card (z-index),
       so container-level listeners would miss most cursor movement. */
    if (o.mouseInteraction) {
        window.addEventListener('mousemove', handleMouseMove);
        document.documentElement.addEventListener('mouseleave', handleMouseLeave);
    }

    return () => {
        cancelAnimationFrame(animateId);
        window.removeEventListener('resize', resize);
        if (o.mouseInteraction) {
            window.removeEventListener('mousemove', handleMouseMove);
            document.documentElement.removeEventListener('mouseleave', handleMouseLeave);
        }
        if (gl.canvas.parentNode === container) container.removeChild(gl.canvas);
        gl.getExtension('WEBGL_lose_context')?.loseContext();
    };
}

/* Auto-mount on pages that provide a #galaxy container (login). */
export function mountLoginGalaxy() {
    const el = document.getElementById('galaxy');
    if (!el || el.dataset.galaxyMounted) return;
    el.dataset.galaxyMounted = '1';
    initGalaxy(el, {
        disableAnimation: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        /* Selalu starfield gelap: background login hitam di kedua mode.
           lightMode asli me-render kanvas putih opaque bertinta gelap. */
        lightMode: false,
    });
}
