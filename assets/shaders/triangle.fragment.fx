precision highp float;

// Uniforms
uniform float iTime;
uniform bool isTap;
uniform float tapTime;
varying vec2 vUV;
// Triangle vertices
vec2 p0 = vec2(0., -0.1);
vec2 p1 = vec2(1.0, -0.1);
vec2 p2 = vec2(0.5, .9);

// Function to calculate distance to a line segment
float lineSegmentSDF(vec2 p, vec2 a, vec2 b) {
    vec2 pa = p - a, ba = b - a;
    float h = clamp(dot(pa, ba) / dot(ba, ba), 0.0, 1.0);
    return length(pa - ba * h);
}

// SDF for the triangle
float triangleSDF(vec2 p) {
    float d0 = lineSegmentSDF(p, p0, p1);
    float d1 = lineSegmentSDF(p, p1, p2);
    float d2 = lineSegmentSDF(p, p2, p0);

    float d = min(min(d0, d1), d2);

    // Check if point is inside the triangle
    bool inside =
        dot(p1 - p0, p - p0) * dot(p1 - p0, p2 - p0) <= 0.0 &&
        dot(p2 - p1, p - p1) * dot(p2 - p1, p0 - p1) <= 0.0 &&
        dot(p0 - p2, p - p2) * dot(p0 - p2, p1 - p2) <= 0.0;

    return inside ? -d : d;
}

// Function to add rounded corners
float roundedTriangleSDF(vec2 p, float radius) {
    float d = triangleSDF(p);
    return d - radius;
}

vec3 getColorOverTime(vec2 uv, float time) {
    // Define colors for the cycle
    vec3 colorStart = vec3(1.0, 0.0, 0.0); // Red
    vec3 colorMid = vec3(0.0, 1.0, 0.0);   // Green
    vec3 colorEnd = vec3(0.0, 0.0, 1.0);   // Blue

    // Calculate a time-based factor
    float t = mod(time, 3.0); // Cycle every 3 seconds

    // Determine which part of the cycle we're in
    if (t < 1.0) {
        // Transition from start to mid
        return mix(colorStart, colorMid, t);
    } else if (t < 2.0) {
        // Transition from mid to end
        return mix(colorMid, colorEnd, t - 1.0);
    } else {
        // Transition from end to start
        return mix(colorEnd, colorStart, t - 2.0);
    }
}

void main() {
 vec2 uv = vUV; // Use the UV coordinates directly
 vec2 pUV = uv;
 pUV -= 0.5;
 uv *= 2.; // Scale
 uv -= 0.5; // Translate
 uv.y=1.-uv.y;

 // Adjust this value for the rounding radius
 float radius = 0.05;

//--
 float d = roundedTriangleSDF(uv, radius);
 float d2 = roundedTriangleSDF(uv * 1.1 - vec2(.05, .03), radius);

//----------------------------
// float d = lineSegmentSDF(uv, p2,p0);
// d = min(d,lineSegmentSDF(uv, p1,p2));
 //----------------------
 // Apply color to the triangle
 vec3 color = vec3(1.0 - smoothstep(0.1, 0.11, d));
 color -= 1.0 - smoothstep(0.1, 0.11, d2);

 vec2 st = vec2(atan(pUV.x,pUV.y),length(pUV));
 pUV = vec2(st.x/(2.*3.14159),st.y);
 float t = iTime;
 if(isTap){
   t*=smoothstep(1.,1.1,iTime);
   t-=1.1+tapTime;
 }
 else{
   t=-0.1;
 }

 color *= vec3(1.-smoothstep(.011+t,.01+t,abs(pUV.x)));
 color = 1.-vec3(1)*smoothstep(.11,.1,color.x);
 if(length(color) <.5){
   discard;
 }
 gl_FragColor = vec4(color, 1.0);
}
