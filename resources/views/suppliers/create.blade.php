@extends('layouts.app')

@section('title', 'Tambah Supplier')
@section('heading', 'Tambah Supplier')
@section('subheading', 'Lengkapi profil supplier dan nilai setiap kriteria')

@section('content')
    @include('suppliers._form', ['supplier' => $supplier, 'criteria' => $criteria])
@endsection
