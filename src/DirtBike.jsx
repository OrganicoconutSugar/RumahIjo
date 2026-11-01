import React, { useLayoutEffect, useMemo, useRef } from 'react';
import * as THREE from 'three';

function Tube({ from, to, radius = .025, color = '#aab0b4', metalness = .7 }) {
  const { midpoint, length, quaternion } = useMemo(() => {
    const start = new THREE.Vector3(...from);
    const end = new THREE.Vector3(...to);
    const direction = end.clone().sub(start);
    return {
      midpoint: start.clone().add(end).multiplyScalar(.5),
      length: direction.length(),
      quaternion: new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), direction.normalize()),
    };
  }, [...from, ...to]);
  return <mesh position={midpoint} quaternion={quaternion} castShadow><cylinderGeometry args={[radius, radius, length, 10]}/><meshStandardMaterial color={color} metalness={metalness} roughness={.36}/></mesh>;
}

function Panel({ points, width, color, position = [0, 0, 0] }) {
  const geometry = useMemo(() => {
    const shape = new THREE.Shape();
    points.forEach(([x, y], index) => index ? shape.lineTo(x, y) : shape.moveTo(x, y));
    shape.closePath();
    const result = new THREE.ExtrudeGeometry(shape, { depth: width, bevelEnabled: true, bevelThickness: .015, bevelSize: .015, bevelSegments: 3, steps: 1 });
    result.translate(0, 0, -width / 2);
    return result;
  }, [points, width]);
  return <mesh position={position} geometry={geometry} castShadow><meshStandardMaterial color={color} roughness={.38} metalness={.15}/></mesh>;
}

function KnobbyWheel({ position, radius, width }) {
  const treadRef = useRef(null);
  const treadCount = 26 * 3;
  useLayoutEffect(() => {
    const matrix = new THREE.Matrix4();
    const quaternion = new THREE.Quaternion();
    for (let row = 0; row < 3; row++) {
      for (let index = 0; index < 26; index++) {
        const angle = (index + (row % 2) * .45) / 26 * Math.PI * 2;
        quaternion.setFromAxisAngle(new THREE.Vector3(0, 0, 1), angle - Math.PI / 2);
        const surfaceRadius = radius + (row === 1 ? .007 : -.007);
        matrix.compose(new THREE.Vector3(Math.cos(angle) * surfaceRadius, Math.sin(angle) * surfaceRadius, (row - 1) * width * .3), quaternion, new THREE.Vector3(1, 1, 1));
        treadRef.current.setMatrixAt(row * 26 + index, matrix);
      }
    }
    treadRef.current.instanceMatrix.needsUpdate = true;
  }, [radius, width]);
  const spokes = useMemo(() => {
    const coordinates = [];
    for (let index = 0; index < 24; index++) {
      const angle = index / 24 * Math.PI * 2;
      coordinates.push(.055 * Math.cos(angle - .28), .055 * Math.sin(angle - .28), index % 2 ? .065 : -.065, (radius - .095) * Math.cos(angle), (radius - .095) * Math.sin(angle), 0);
    }
    return new Float32Array(coordinates);
  }, [radius]);
  return <group position={position}>
    <mesh castShadow><torusGeometry args={[radius - .052, .054, 10, 36]}/><meshStandardMaterial color="#1c1e20" roughness={.94}/></mesh>
    <instancedMesh ref={treadRef} args={[null, null, treadCount]} castShadow><boxGeometry args={[.057, .025, width * .27]}/><meshStandardMaterial color="#242629" roughness={.95}/></instancedMesh>
    <mesh><torusGeometry args={[radius - .095, .017, 8, 36]}/><meshStandardMaterial color="#c8cdd0" metalness={.85} roughness={.24}/></mesh>
    <mesh rotation={[Math.PI / 2, 0, 0]}><cylinderGeometry args={[.062, .062, width, 16]}/><meshStandardMaterial color="#9da5ac" metalness={.8} roughness={.3}/></mesh>
    <lineSegments><bufferGeometry><bufferAttribute attach="attributes-position" args={[spokes, 3]}/></bufferGeometry><lineBasicMaterial color="#c5cbd0"/></lineSegments>
    <mesh position={[0, 0, width * .53]}><ringGeometry args={[.075, .135, 24]}/><meshStandardMaterial color="#8b9398" metalness={.8} roughness={.4} side={THREE.DoubleSide}/></mesh>
  </group>;
}

function CurvedTube({ points, radius, color }) {
  const curve = useMemo(() => new THREE.CatmullRomCurve3(points.map(point => new THREE.Vector3(...point))), [points]);
  return <mesh castShadow><tubeGeometry args={[curve, 24, radius, 8, false]}/><meshStandardMaterial color={color} metalness={.7} roughness={.35}/></mesh>;
}

export default function DirtBike({ position, color, rotation = 0 }) {
  const springPoints = useMemo(() => {
    const bottom = new THREE.Vector3(-.5, .47, 0);
    const top = new THREE.Vector3(-.17, .88, 0);
    const axis = top.clone().sub(bottom).normalize();
    const sideways = new THREE.Vector3(0, 0, 1);
    const outward = axis.clone().cross(sideways).normalize();
    return Array.from({ length: 97 }, (_, index) => {
      const progress = index / 96;
      const angle = progress * Math.PI * 12;
      return bottom.clone().lerp(top, progress).addScaledVector(sideways, Math.cos(angle) * .055).addScaledVector(outward, Math.sin(angle) * .055).toArray();
    });
  }, []);
  return <group position={position} rotation={[0, rotation, 0]} scale={.72}>
    <KnobbyWheel position={[-.77, .38, 0]} radius={.365} width={.16}/>
    <KnobbyWheel position={[.83, .415, 0]} radius={.4} width={.13}/>
    {/* Double cradle frame and a rear swingarm leave the engine visible. */}
    {[-.1, .1].map(z => <group key={z}>
      <Tube from={[-.48, .51, z]} to={[-.18, .96, z]} color="#353a3e"/>
      <Tube from={[-.18, .96, z]} to={[.38, 1.12, z]} color="#353a3e"/>
      <Tube from={[.38, 1.12, z]} to={[.24, .43, z]} color="#353a3e"/>
      <Tube from={[.24, .43, z]} to={[-.36, .43, z]} color="#353a3e"/>
      <Tube from={[-.36, .43, z]} to={[-.48, .51, z]} color="#353a3e"/>
      <Tube from={[-.77, .38, z]} to={[-.31, .51, z]} radius={.035}/>
      <Tube from={[-.55, 1.03, z]} to={[-.32, .53, z]} radius={.018} color="#45494d"/>
      {/* Raked long-travel front fork, gold upper tubes and silver sliders. */}
      <Tube from={[.4, 1.23, z]} to={[.65, .78, z]} radius={.032} color="#c2a773"/>
      <Tube from={[.65, .78, z]} to={[.83, .415, z]} radius={.027}/>
    </group>)}
    <CurvedTube points={springPoints} radius={.012} color="#c6a64b"/>
    {/* Cast engine cases, finned cylinder, cooling hoses and exhaust. */}
    <mesh position={[-.12, .57, 0]} rotation={[Math.PI / 2, 0, 0]}><cylinderGeometry args={[.15, .15, .28, 20]}/><meshStandardMaterial color="#8c9396" metalness={.7} roughness={.4}/></mesh>
    <mesh position={[.1, .64, 0]} rotation={[0, 0, -.2]}><cylinderGeometry args={[.1, .115, .25, 12]}/><meshStandardMaterial color="#5b6268" metalness={.7} roughness={.4}/></mesh>
    {[.58, .62, .66, .7, .74].map(y => <mesh key={y} position={[.1, y, 0]}><cylinderGeometry args={[.115, .115, .016, 12]}/><meshStandardMaterial color="#a7afb3" metalness={.8} roughness={.35}/></mesh>)}
    <CurvedTube points={[[.2, .74, .13], [.39, .62, .19], [.14, .48, .22], [-.32, .64, .23], [-.6, .88, .23]]} radius={.035} color="#8e9294"/>
    <Tube from={[-.48, .86, .22]} to={[-.93, .98, .22]} radius={.067} color="#a4a8aa"/>
    {/* Thin, bevelled plastic fairings follow the dirt-bike silhouette. */}
    <Panel points={[[-.24, .88], [-.12, 1.09], [.24, 1.11], [.41, 1.02], [.23, .8]]} width={.27} color={color}/>
    <Panel points={[[-.66, .91], [-.53, 1.06], [-.2, 1.04], [-.3, .78]]} width={.25} color="#eceef0"/>
    <Panel points={[[-.62, 1.055], [-.42, 1.105], [.12, 1.12], [.17, 1.055]]} width={.235} color="#24282c"/>
    <Panel points={[[-1.07, 1.02], [-1.03, 1.065], [-.62, 1.08], [-.42, 1.04], [-.64, .99]]} width={.19} color={color}/>
    <Panel points={[[.38, .98], [.55, 1.02], [1.1, 1.015], [1.17, .97], [.61, .97]]} width={.18} color={color}/>
    <Panel points={[[.44, 1.09], [.55, 1.08], [.53, 1.31], [.4, 1.33]]} width={.22} color="#f0f1ed"/>
    <mesh position={[.558, 1.2, 0]} scale={[.025, .055, .07]}><sphereGeometry args={[1, 12, 10]}/><meshStandardMaterial color="#eef2e8" emissive="#d4d3a8" emissiveIntensity={.2}/></mesh>
    {/* Raised bars, separate grips, foot pegs, brake lever and parking stand. */}
    <Tube from={[.4, 1.22, 0]} to={[.38, 1.36, 0]} radius={.025}/>
    <Tube from={[.38, 1.36, -.17]} to={[.38, 1.36, .17]} radius={.019}/>
    {[-1, 1].map(side => <group key={side}>
      <Tube from={[.38, 1.36, side * .17]} to={[.32, 1.42, side * .28]} radius={.019}/>
      <Tube from={[.32, 1.42, side * .28]} to={[.32, 1.42, side * .38]} radius={.029} color="#222529" metalness={.1}/>
      <Tube from={[-.27, .48, side * .1]} to={[-.27, .48, side * .24]} radius={.028} color="#41474b"/>
    </group>)}
    <Tube from={[.38, 1.42, .28]} to={[.43, 1.41, .39]} radius={.01}/>
    <Tube from={[-.32, .45, -.14]} to={[-.52, .025, -.33]} radius={.019} color="#4d565b"/>
    <mesh position={[-1.025, 1.045, 0]} scale={[.045, .025, .06]}><sphereGeometry args={[1, 10, 8]}/><meshStandardMaterial color="#c32627"/></mesh>
  </group>;
}
