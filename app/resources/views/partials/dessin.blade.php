{{--
  Le dessin d'un article.

  Des illustrations vectorielles plutôt que des photographies : elles restent
  nettes à toute taille, ne pèsent rien, ne posent aucune question de droits, et
  surtout elles sont cohérentes entre elles — vingt photographies glanées ici et
  là donneraient vingt cadrages, vingt lumières, vingt fonds.

  Chaque article porte dans ses caractéristiques la clef « dessin » ; on retombe
  sur un profil générique si elle manque.

  Variables attendues : $dessin (string), $taille (int, défaut 96)
--}}
@php $t = $taille ?? 96; @endphp

<svg viewBox="0 0 120 120" width="{{ $t }}" height="{{ $t }}" fill="none"
     xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true"
     style="display:block">

  @switch($dessin)

    @case('rond-strie')
      {{-- Une barre en perspective, avec les stries de l'adhérence. --}}
      <rect x="14" y="46" width="92" height="28" rx="14" fill="url(#g-acier)"/>
      @for($i = 0; $i < 7; $i++)
        <path d="M{{ 24 + $i * 12 }} 48 l7 24" stroke="#52606D" stroke-width="3" stroke-linecap="round" opacity=".55"/>
      @endfor
      <ellipse cx="14" cy="60" rx="5" ry="14" fill="#52606D"/>
      @break

    @case('rond-lisse')
      <rect x="14" y="46" width="92" height="28" rx="14" fill="url(#g-acier)"/>
      <path d="M20 54 h80" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".45"/>
      <ellipse cx="14" cy="60" rx="5" ry="14" fill="#52606D"/>
      @break

    @case('tole-bac')
      {{-- Les nervures du bac, vues de bout. --}}
      <path d="M10 78 l14-30 14 30 14-30 14 30 14-30 14 30" stroke="url(#g-acier)"
            stroke-width="9" stroke-linejoin="round" stroke-linecap="round"/>
      <path d="M10 90 l14-30 14 30 14-30 14 30 14-30 14 30" stroke="#52606D"
            stroke-width="3" stroke-linejoin="round" opacity=".35"/>
      @break

    @case('tole-ondulee')
      <path d="M10 66 q10-20 20 0 t20 0 t20 0 t20 0 t20 0" stroke="url(#g-acier)"
            stroke-width="10" stroke-linecap="round" fill="none"/>
      <path d="M10 82 q10-20 20 0 t20 0 t20 0 t20 0 t20 0" stroke="#52606D"
            stroke-width="3" opacity=".3" fill="none"/>
      @break

    @case('tole-plane')
      <path d="M18 40 h74 l10 14 v26 h-74 l-10-14 z" fill="url(#g-acier)"/>
      <path d="M18 40 l10 14 h74 M28 54 v26" stroke="#52606D" stroke-width="2.5" opacity=".5"/>
      @break

    @case('tole-larmee')
      <path d="M18 40 h74 l10 14 v26 h-74 l-10-14 z" fill="url(#g-acier)"/>
      @for($y = 0; $y < 3; $y++)
        @for($x = 0; $x < 5; $x++)
          <rect x="{{ 26 + $x * 14 + ($y % 2) * 6 }}" y="{{ 58 + $y * 8 }}" width="9" height="3.5"
                rx="1.7" fill="#52606D" opacity=".55" transform="rotate(-28 {{ 30 + $x * 14 }} {{ 60 + $y * 8 }})"/>
        @endfor
      @endfor
      @break

    @case('corniere')
      <path d="M26 26 h14 v54 h54 v14 h-68 z" fill="url(#g-acier)"/>
      <path d="M26 26 h14 v54 h54" stroke="#52606D" stroke-width="2.5" fill="none" opacity=".55"/>
      @break

    @case('tube-carre')
      <rect x="26" y="30" width="60" height="60" rx="4" fill="url(#g-acier)"/>
      <rect x="40" y="44" width="32" height="32" rx="3" fill="#F7F8FA"/>
      @break

    @case('tube-rect')
      <rect x="18" y="38" width="84" height="44" rx="4" fill="url(#g-acier)"/>
      <rect x="31" y="50" width="58" height="20" rx="3" fill="#F7F8FA"/>
      @break

    @case('tube-rond')
      <circle cx="60" cy="60" r="32" fill="url(#g-acier)"/>
      <circle cx="60" cy="60" r="19" fill="#F7F8FA"/>
      @break

    @case('fer-plat')
      <rect x="14" y="52" width="92" height="16" rx="3" fill="url(#g-acier)"/>
      <path d="M14 56 h92" stroke="#fff" stroke-width="2.5" opacity=".5"/>
      @break

    @case('upn')
      <path d="M30 26 h22 v14 h16 v-14 h22 v68 h-22 v-14 h-16 v14 h-22 z"
            fill="url(#g-acier)" transform="rotate(90 60 60)"/>
      @break

    @case('ipn')
      <path d="M28 28 h64 v13 h-25 v38 h25 v13 h-64 v-13 h25 v-38 h-25 z" fill="url(#g-acier)"/>
      @break

    @case('treillis')
      @for($i = 0; $i < 5; $i++)
        <path d="M{{ 22 + $i * 19 }} 24 v72" stroke="url(#g-acier)" stroke-width="5" stroke-linecap="round"/>
        <path d="M20 {{ 28 + $i * 17 }} h80" stroke="url(#g-acier)" stroke-width="5" stroke-linecap="round"/>
      @endfor
      @break

    @case('fil')
      <circle cx="60" cy="60" r="34" stroke="url(#g-acier)" stroke-width="7" fill="none"/>
      <circle cx="60" cy="60" r="23" stroke="#8794A1" stroke-width="6" fill="none"/>
      <circle cx="60" cy="60" r="13" stroke="#6B7784" stroke-width="5" fill="none"/>
      @break

    @case('grillage')
      @for($i = 0; $i < 6; $i++)
        <path d="M{{ 18 + $i * 17 }} 22 v76" stroke="#8794A1" stroke-width="2.5"/>
        <path d="M16 {{ 26 + $i * 14 }} h88" stroke="#8794A1" stroke-width="2.5"/>
      @endfor
      <rect x="16" y="22" width="88" height="76" rx="4" stroke="#52606D" stroke-width="4" fill="none"/>
      @break

    @case('clou')
      @foreach([[42, -14], [60, 0], [78, 14]] as [$x, $r])
        <g transform="rotate({{ $r }} {{ $x }} 60)">
          <path d="M{{ $x }} 32 v50" stroke="url(#g-acier)" stroke-width="6" stroke-linecap="round"/>
          <path d="M{{ $x - 9 }} 32 h18" stroke="#52606D" stroke-width="6" stroke-linecap="round"/>
          <path d="M{{ $x }} 82 l4 8 h-8 z" fill="#52606D"/>
        </g>
      @endforeach
      @break

    @case('vis')
      <path d="M60 30 v54" stroke="url(#g-acier)" stroke-width="11" stroke-linecap="round"/>
      @for($i = 0; $i < 8; $i++)
        <path d="M53 {{ 38 + $i * 6 }} l14 4" stroke="#52606D" stroke-width="2.5" opacity=".7"/>
      @endfor
      <circle cx="60" cy="30" r="14" fill="#52606D"/>
      <path d="M52 30 h16 M60 22 v16" stroke="#CBD2D9" stroke-width="3.5" stroke-linecap="round"/>
      <path d="M60 84 l6 12 h-12 z" fill="#52606D"/>
      @break

    @case('boulon')
      <path d="M60 22 l22 13 v26 l-22 13 -22-13 v-26 z" fill="url(#g-acier)"/>
      <circle cx="60" cy="48" r="10" fill="#F7F8FA"/>
      <path d="M60 74 v24" stroke="#8794A1" stroke-width="10" stroke-linecap="round"/>
      @for($i = 0; $i < 4; $i++)
        <path d="M54 {{ 80 + $i * 5 }} h12" stroke="#52606D" stroke-width="2" opacity=".7"/>
      @endfor
      @break

    @case('cheville')
      <rect x="48" y="26" width="24" height="68" rx="11" fill="url(#g-acier)"/>
      <path d="M60 34 v52" stroke="#52606D" stroke-width="3" opacity=".6"/>
      <path d="M48 60 h24 M48 74 h24" stroke="#52606D" stroke-width="2.5" opacity=".5"/>
      @break

    @case('cadenas')
      <path d="M42 54 v-12 a18 18 0 0 1 36 0 v12" stroke="url(#g-acier)"
            stroke-width="9" fill="none" stroke-linecap="round"/>
      <rect x="32" y="52" width="56" height="44" rx="8" fill="url(#g-forge)"/>
      <circle cx="60" cy="72" r="7" fill="#7A2E06"/>
      <path d="M60 72 v12" stroke="#7A2E06" stroke-width="5" stroke-linecap="round"/>
      @break

    @case('charniere')
      <rect x="24" y="30" width="32" height="60" rx="3" fill="url(#g-acier)"/>
      <rect x="64" y="30" width="32" height="60" rx="3" fill="url(#g-acier)"/>
      <rect x="55" y="24" width="10" height="72" rx="5" fill="#52606D"/>
      @foreach([[38, 46], [38, 74], [82, 46], [82, 74]] as [$x, $y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#F7F8FA"/>
      @endforeach
      @break

    @case('electrode')
      @foreach([[44, -10], [60, 0], [76, 10]] as [$x, $r])
        <g transform="rotate({{ $r }} {{ $x }} 60)">
          <path d="M{{ $x }} 26 v68" stroke="#8794A1" stroke-width="7" stroke-linecap="round"/>
          <path d="M{{ $x }} 34 v52" stroke="url(#g-forge)" stroke-width="7"/>
        </g>
      @endforeach
      @break

    @case('disque')
      <circle cx="60" cy="60" r="38" fill="url(#g-acier)"/>
      <circle cx="60" cy="60" r="38" stroke="#52606D" stroke-width="3" fill="none"/>
      <circle cx="60" cy="60" r="18" fill="url(#g-forge)"/>
      <circle cx="60" cy="60" r="7" fill="#F7F8FA"/>
      @break

    @case('brouette')
      <path d="M24 40 h56 l14 26 h-56 z" fill="url(#g-forge)"/>
      <path d="M80 66 l16 18" stroke="#52606D" stroke-width="6" stroke-linecap="round"/>
      <path d="M38 66 v20" stroke="#52606D" stroke-width="6" stroke-linecap="round"/>
      <circle cx="52" cy="84" r="14" fill="#323F4B"/>
      <circle cx="52" cy="84" r="6" fill="#CBD2D9"/>
      @break

    @case('pelle')
      <path d="M60 22 v46" stroke="#A8763E" stroke-width="8" stroke-linecap="round"/>
      <path d="M52 22 h16" stroke="#A8763E" stroke-width="8" stroke-linecap="round"/>
      <path d="M40 68 h40 a20 26 0 0 1-40 0 z" fill="url(#g-acier)"/>
      @break

    @case('truelle')
      <path d="M60 24 v22" stroke="#A8763E" stroke-width="8" stroke-linecap="round"/>
      <path d="M60 46 l34 24 -34 26 -34-26 z" fill="url(#g-acier)"/>
      @break

    @case('marteau')
      <path d="M34 30 h34 v22 h-34 a11 11 0 0 1 0-22 z" fill="url(#g-acier)"/>
      <path d="M68 34 l18 -6 v22 l-18-6 z" fill="#52606D"/>
      <path d="M56 52 v44" stroke="#A8763E" stroke-width="9" stroke-linecap="round"/>
      @break

    @case('roulement')
      <circle cx="60" cy="60" r="38" fill="url(#g-acier)"/>
      <circle cx="60" cy="60" r="38" stroke="#52606D" stroke-width="3" fill="none"/>
      <circle cx="60" cy="60" r="17" fill="#F7F8FA" stroke="#52606D" stroke-width="3"/>
      @for($i = 0; $i < 8; $i++)
        <circle cx="{{ 60 + 27 * cos($i * M_PI / 4) }}" cy="{{ 60 + 27 * sin($i * M_PI / 4) }}"
                r="6" fill="#F7F8FA" stroke="#52606D" stroke-width="2"/>
      @endfor
      @break

    @case('courroie')
      <ellipse cx="60" cy="60" rx="42" ry="26" stroke="#323F4B" stroke-width="11" fill="none"/>
      <ellipse cx="60" cy="60" rx="42" ry="26" stroke="#52606D" stroke-width="3" fill="none"/>
      @break

    @case('chaine')
      @for($i = 0; $i < 4; $i++)
        <rect x="{{ 14 + $i * 24 }}" y="48" width="30" height="24" rx="12"
              stroke="url(#g-acier)" stroke-width="6" fill="none"/>
      @endfor
      @break

    @case('poulie')
      <circle cx="60" cy="60" r="38" fill="url(#g-acier)"/>
      <circle cx="60" cy="60" r="30" stroke="#52606D" stroke-width="6" fill="none"/>
      <circle cx="60" cy="60" r="10" fill="#F7F8FA" stroke="#52606D" stroke-width="3"/>
      @break

    @case('roue')
      <circle cx="60" cy="60" r="38" fill="#323F4B"/>
      <circle cx="60" cy="60" r="24" fill="url(#g-acier)"/>
      <circle cx="60" cy="60" r="8" fill="#52606D"/>
      @for($i = 0; $i < 12; $i++)
        <path d="M{{ 60 + 38 * cos($i * M_PI / 6) }} {{ 60 + 38 * sin($i * M_PI / 6) }}
                 L{{ 60 + 31 * cos($i * M_PI / 6) }} {{ 60 + 31 * sin($i * M_PI / 6) }}"
              stroke="#52606D" stroke-width="4"/>
      @endfor
      @break

    @default
      {{-- Profil générique : une barre d'acier, ce que la maison vend le plus. --}}
      <rect x="18" y="48" width="84" height="24" rx="6" fill="url(#g-acier)"/>
      <path d="M24 54 h72" stroke="#fff" stroke-width="2.5" opacity=".45"/>
  @endswitch
</svg>
