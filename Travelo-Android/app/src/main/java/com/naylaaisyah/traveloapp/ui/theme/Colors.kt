package com.naylaaisyah.traveloapp.ui.theme

import androidx.compose.ui.graphics.Color

// ─────────────────────────────────────────────────────────────
//  Travelo "Cute & Dreamy" — Android design tokens
//
//  This file is the single source of truth for colour on Android.
//  It mirrors the web tokens (resources/css/app.css) and the XML
//  theme (res/values/colors.xml).
//
//  Contrast, measured against VanillaCream (#FFF9F7):
//    Rosewood  #A95372  4.80:1  AA  → primary CTA, links, prices
//    Plum Ink  #493747 10.40:1  AAA → body copy & headings
//    PlumMuted #6E5A68  5.30:1  AA  → secondary copy
//    SageDeep  #4F7D66  4.71:1  AA  → success text/icons
//    CottonCandy #E9A0B8 1.96:1  ✗  → decorative fills only
//    SageMint  #A9C9B7  1.79:1  ✗  → decorative fills only
// ─────────────────────────────────────────────────────────────

// Brand
val Rosewood = Color(0xFFA95372)
val RosewoodPressed = Color(0xFF8F4460)
val CottonCandy = Color(0xFFE9A0B8)
val BlushMist = Color(0xFFF9E7ED)
val VanillaCream = Color(0xFFFFF9F7)
val PlumInk = Color(0xFF493747)
val SageMint = Color(0xFFA9C9B7)

// Derived accessibility / state tokens
val SageDeep = Color(0xFF4F7D66)
val PlumMuted = Color(0xFF6E5A68)
val PlumBorder = Color(0xFFE7D9DF)
val DangerDeep = Color(0xFFB03A4E)
val WarningDeep = Color(0xFF8F5F36)

val SageTint = Color(0xFFE7F0EA)
val DangerTint = Color(0xFFF7E3E7)
val WarningTint = Color(0xFFF7E8D8)
val RoseTint = Color(0xFFF6E9EE)

val SurfaceWhite = Color(0xFFFFFFFF)

// ─────────────────────────────────────────────────────────────
//  Legacy aliases
//  Kept so screens that have not been migrated yet keep compiling.
//  New code must use the tokens above instead of these names.
// ─────────────────────────────────────────────────────────────
val Primary = Rosewood
val PrimaryLight = BlushMist
val PrimaryDark = RosewoodPressed
val Secondary = CottonCandy
val SecondaryLight = BlushMist
val SecondaryDark = Rosewood
val Info = SageDeep

val Background = VanillaCream
val OnBackground = PlumInk
val Surface = SurfaceWhite
val OnSurface = PlumInk
val SurfaceVariant = BlushMist
val OnSurfaceVariant = PlumMuted
val NavPink = BlushMist

val Error = DangerDeep
val OnPrimary = Color(0xFFFFFFFF)
val OnSecondary = PlumInk

val Gray900 = PlumInk
val Gray800 = Color(0xFF5A4755)
val Gray700 = PlumMuted
val Gray600 = Color(0xFF85717D)
val Gray500 = Color(0xFF9C8894)
val Gray400 = Color(0xFFB9A6B0)
val Gray300 = Color(0xFFD9C8D0)
val Gray200 = Color(0xFFEBDDE2)
val Gray100 = Color(0xFFF7F0F2)

val Success = SageDeep
val Warning = WarningDeep
