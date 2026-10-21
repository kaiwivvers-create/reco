@extends('layouts.app')

@section('title', 'Edit Supplier')
@section('heading', 'Edit Supplier')
@section('subheading', 'Perbarui profil atau nilai kriteria supplier')

@section('actions')
    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary btn-sm">
        <x-icon name="eye" class="h-3.5 w-3.5" /> Detail
    </a>
@endsection

@section('content')
    @include('suppliers._form', ['supplier' => $supplier, 'criteria' => $criteria])
@endsection
